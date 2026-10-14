<?php

namespace Neobit\Core\Team;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Mail\MailTemplateException;
use Neobit\Core\Mail\TemplatedMail;
use Neobit\Core\Models\TeamInvitation;
use Neobit\Core\Operations\OperationRecorder;

/**
 * 集団への招待の、発行・送り直し・取り消し・照合。企業のような集団に、仲間を招待して足す。
 * 集団は、共通の型（TeamInvitationOwner）で受け取る。企業などの特定のモデルは名指ししない。
 *
 * 仲間は、招待のメールのリンクから本人が登録して足す。招待する側がログインIDやパスワードを
 * 決めて渡す形にしないのは、パスワードを本人のほかに知っている人を作らないため。
 * リンクは宛先のメールアドレスにしか届かないので、リンクから登録した時点で、
 * メールアドレスは確かめられたことになる。
 * リンクから登録する画面は、項目が集団ごとに違うので、この部品には無い。プロジェクトの
 * コントローラーが、find()で招待を確かめて登録し、済んだら招待の行を消す。
 *
 * ■ リンクの値
 * ランダムな値を作ってリンクに入れ、DBにはSHA-256のハッシュ値だけを置く。DBが漏れても、
 * そこからリンクを作れないようにするため。信頼済み端末のCookie（TrustedDeviceManager）と同じ扱い。
 * リンクの値はDBに残らないので、送り直すときは新しい値を作り、前のリンクは使えなくなる。
 *
 * ■ 期限と片付け
 * 招待はVALID_DAYSの日数だけ有効。登録が済んだ招待と、取り消した招待は、行を消す。
 * 期限の切れた招待は使われず、一時データの後片付けがdeleteExpired()を呼んで消す
 * （config/cleanup.phpのtasks）。
 *
 * ■ 招待した人
 * 招待を作ったときに、その画面でログインしている人を、招待した人として行に残す。仲間のことも、
 * 管理画面のスタッフのこともある。その後は変えない。
 * 送り直せるのは、招待した本人だけ（TeamInvitation::isSentBy()。呼ぶ側のコントローラーが確かめる）。
 * ほかの人が同じ宛先を招きたいときは、新しく招待する。前の招待は消えて、新しい番号の招待になり、
 * 新しく送った人が、招待した人になる。取り消しは、本人でなくてもできる。宛先を間違えた招待を、
 * 送った人がいないときにも止められるようにするため。
 *
 * ■ 操作ログ
 * 招待の送信と取り消しを、集団を対象にして残す。宛先のメールアドレスは残さない。
 * 操作した人は、その画面のガードでログインしている人。補足に、招待の番号を「招待ID:12」の形で
 * 残す（logDetail()）。招待から登録したときの記録にも同じ番号を残すと、誰が送った招待から
 * 誰が登録したかを、操作ログの補足の検索でたどれる。
 *
 * ■ メール
 * テンプレートは、招待から登録される人の種類の「種類の名前_invitation」。無ければ、種類の名前を
 * 除いたinvitationを使う。登録の画面のルートは
 * 「種類の名前.invitation.show」で、リンクの値をtokenで渡す。どちらも、集団のモデルの
 * invitedAccountClass()から決まる。
 * テンプレートに渡す値：
 * - site_name     サイトの名前
 * - inviter_name  招待した人の氏名。招待から登録される人と同じ種類の人（仲間）が招待したときだけ入る。
 *                 管理画面のスタッフが招待したときは空。スタッフの氏名を、外の人へのメールに出さないため
 * - url           登録の画面のURL
 * - expires_at    リンクの期限
 * メールは保存が確定した後に送り、送れなくても招待は取り消さずにログにだけ残す。
 * 届かなかったときは、一覧の「送り直す」でやり直せる。
 */
final class TeamInvitationManager
{
    /** 招待の有効な日数 */
    public const VALID_DAYS = 7;

    /** リンクに入れる値の長さ */
    private const TOKEN_LENGTH = 64;

    /** メールのテンプレートと、登録の画面のルートの、種類の名前を除いた名前 */
    private const TEMPLATE = 'invitation';

    private const ROUTE = 'invitation.show';

    /** 招待を作って、メールを送る。同じ集団の同じ宛先の招待が残っていれば、置き換える */
    public static function invite(Model&TeamInvitationOwner $team, string $email): void
    {
        DB::transaction(function () use ($team, $email) {
            $team->invitations()->where('email', $email)->delete();

            $token = Str::random(self::TOKEN_LENGTH);

            // 招待した人は、その画面でログインしている人
            $inviter = Auth::user();

            $invitation = $team->invitations()->create([
                'email' => $email,
                'token_hash' => self::hashOf($token),
                'expires_at' => now()->addDays(self::VALID_DAYS),
                'inviter_type' => $inviter?->getMorphClass(),
                'inviter_id' => $inviter?->getKey(),
            ]);

            OperationRecorder::record(OperationLogAction::InvitationSend, $team, detail: self::logDetail($invitation));

            DB::afterCommit(fn () => self::sendMail($invitation, $token));
        });
    }

    /**
     * 招待のメールを送り直す。リンクの値と期限を新しくするので、前のリンクは使えなくなる。
     * 招待の番号と、招待した人は変わらない。招待した本人かどうかは、呼ぶ側が確かめておく
     */
    public static function resend(TeamInvitation $invitation): void
    {
        DB::transaction(function () use ($invitation) {
            $token = Str::random(self::TOKEN_LENGTH);

            $invitation->update([
                'token_hash' => self::hashOf($token),
                'expires_at' => now()->addDays(self::VALID_DAYS),
            ]);

            OperationRecorder::record(OperationLogAction::InvitationSend, $invitation->team, detail: self::logDetail($invitation));

            DB::afterCommit(fn () => self::sendMail($invitation, $token));
        });
    }

    /** 招待を取り消す。送ったリンクは使えなくなる */
    public static function cancel(TeamInvitation $invitation): void
    {
        DB::transaction(function () use ($invitation) {
            $invitation->delete();

            OperationRecorder::record(OperationLogAction::InvitationCancel, $invitation->team, detail: self::logDetail($invitation));
        });
    }

    /** リンクの値から、期限内の招待を探す。無ければnull */
    public static function find(string $token): ?TeamInvitation
    {
        return TeamInvitation::query()
            ->where('token_hash', self::hashOf($token))
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * その集団の、期限内の招待。一覧に「招待中」として出す。
     *
     * @return Collection<int, TeamInvitation>
     */
    public static function pending(TeamInvitationOwner $team): Collection
    {
        return $team->invitations()
            ->with('inviter')
            ->where('expires_at', '>', now())
            ->orderBy('id')
            ->get();
    }

    /** 期限の切れた招待を消す。消した件数を返す。どの集団のものも消す */
    public static function deleteExpired(): int
    {
        return TeamInvitation::query()->where('expires_at', '<=', now())->delete();
    }

    /** リンクの値のハッシュ値。乱数なので、検索できる速いハッシュ値で足りる */
    private static function hashOf(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * 操作ログの補足に残す、招待の番号。招待の送信・取り消しと、招待からの登録の記録に、同じ形で残す。
     *
     * @return array{invitation: string}
     */
    public static function logDetail(TeamInvitation $invitation): array
    {
        return ['invitation' => '招待ID:'.$invitation->id];
    }

    /**
     * 招待のメール。招待した人の氏名と、登録の画面へのリンクと、期限を載せる。
     * 氏名を載せるのは、仲間が招待したときだけ。表示名（displayName()）ではなく氏名を使うのは、
     * 同じ集団の知り合いからの招待なので、集団の名前を添えなくても誰か分かるため。
     */
    private static function sendMail(TeamInvitation $invitation, string $token): void
    {
        try {
            $account = $invitation->team::invitedAccountClass();
            $inviterName = $invitation->isSentByPeer() ? (string) $invitation->inviter?->name : '';

            // テンプレートは、その種類のもの、無ければ種類の名前を除いたものを使う
            Mail::send(new TemplatedMail([$account::loginMailTemplate(self::TEMPLATE), self::TEMPLATE], [
                'from_mail' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'to_mail' => $invitation->email,
                'site_name' => config('app.site_name'),
                'inviter_name' => $inviterName,
                'url' => route($account::loginRoute(self::ROUTE), ['token' => $token]),
                'expires_at' => $invitation->expires_at->format('Y年n月j日 H:i'),
            ]));
        } catch (MailTemplateException $e) {
            // テンプレートが無い、書き方が誤っている、は開発中の誤りなので、受け止めずに止める
            throw $e;
        } catch (\Throwable $e) {
            // メールアドレスは個人情報なので、ログには出さない
            Log::error('TeamInvitationManager: 招待のメールの送信に失敗しました。', [
                'team_type' => $invitation->team_type,
                'team_id' => $invitation->team_id,
                'invitation_id' => $invitation->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
