<?php

namespace App\Support;

use App\Enums\OperationLogAction;
use App\Mail\TemplatedMail;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\CompanyUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * 企業会員の担当者の招待の、発行・送り直し・取り消し・照合。
 *
 * 担当者は、招待のメールのリンクから本人が登録して足す。招待する側が担当者IDやパスワードを
 * 決めて渡す形にしないのは、パスワードを本人のほかに知っている人を作らないため。
 * リンクは宛先のメールアドレスにしか届かないので、リンクから登録した時点で、
 * メールアドレスは確かめられたことになる。
 * 企業の担当者のマイページ（Company\UserController）と、管理画面（Admin\CompanyController）の
 * 両方から使う。登録の画面はCompany\InvitationController。
 *
 * ■ リンクの値
 * ランダムな値を作ってリンクに入れ、DBにはSHA-256のハッシュ値だけを置く。DBが漏れても、
 * そこからリンクを作れないようにするため。信頼済み端末のCookie（TrustedDeviceManager）と同じ扱い。
 * リンクの値はDBに残らないので、送り直すときは新しい値を作り、前のリンクは使えなくなる。
 *
 * ■ 期限と片付け
 * 招待はVALID_DAYSの日数だけ有効。登録が済んだ招待と、取り消した招待は、行を消す。
 * 期限の切れた招待は使われず、TemporaryDataCleanerが消す。
 *
 * ■ 操作ログ
 * 招待の送信と取り消しを、企業を対象にして残す。宛先のメールアドレスは残さない。
 * 操作した人は、その画面のガードでログインしている人。企業の担当者か、スタッフ。
 *
 * ■ メール
 * メールは保存が確定した後に送り、送れなくても招待は取り消さずにログにだけ残す。
 * 届かなかったときは、一覧の「送り直す」でやり直せる。
 */
final class CompanyInvitationManager
{
    // 招待の有効な日数
    public const VALID_DAYS = 7;

    // リンクに入れる値の長さ
    private const TOKEN_LENGTH = 64;

    // メールのテンプレートの、会員の種類の名前を除いた名前。例：company_invitation
    private const TEMPLATE = 'invitation';

    // 招待を作って、メールを送る。同じ企業の同じ宛先の招待が残っていれば、置き換える
    public static function invite(Company $company, string $email): void
    {
        DB::transaction(function () use ($company, $email) {
            $company->invitations()->where('email', $email)->delete();

            $token = Str::random(self::TOKEN_LENGTH);

            $invitation = $company->invitations()->create([
                'email' => $email,
                'token_hash' => self::hashOf($token),
                'expires_at' => now()->addDays(self::VALID_DAYS),
            ]);

            OperationRecorder::record(OperationLogAction::InvitationSend, $company);

            DB::afterCommit(fn () => self::sendMail($invitation, $token));
        });
    }

    // 招待のメールを送り直す。リンクの値と期限を新しくするので、前のリンクは使えなくなる
    public static function resend(CompanyInvitation $invitation): void
    {
        DB::transaction(function () use ($invitation) {
            $token = Str::random(self::TOKEN_LENGTH);

            $invitation->update([
                'token_hash' => self::hashOf($token),
                'expires_at' => now()->addDays(self::VALID_DAYS),
            ]);

            OperationRecorder::record(OperationLogAction::InvitationSend, $invitation->company);

            DB::afterCommit(fn () => self::sendMail($invitation, $token));
        });
    }

    // 招待を取り消す。送ったリンクは使えなくなる
    public static function cancel(CompanyInvitation $invitation): void
    {
        DB::transaction(function () use ($invitation) {
            $invitation->delete();

            OperationRecorder::record(OperationLogAction::InvitationCancel, $invitation->company);
        });
    }

    // リンクの値から、期限内の招待を探す。無ければnull
    public static function find(string $token): ?CompanyInvitation
    {
        return CompanyInvitation::query()
            ->where('token_hash', self::hashOf($token))
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * その企業の、期限内の招待。一覧に「招待中」として出す。
     *
     * @return Collection<int, CompanyInvitation>
     */
    public static function pending(Company $company): Collection
    {
        return $company->invitations()
            ->where('expires_at', '>', now())
            ->orderBy('id')
            ->get();
    }

    // リンクの値のハッシュ値。乱数なので、検索できる速いハッシュ値で足りる
    private static function hashOf(string $token): string
    {
        return hash('sha256', $token);
    }

    // 招待のメール。登録の画面へのリンクと、期限を載せる
    private static function sendMail(CompanyInvitation $invitation, string $token): void
    {
        try {
            Mail::send(new TemplatedMail(CompanyUser::memberMailTemplate(self::TEMPLATE), [
                'from_mail' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'to_mail' => $invitation->email,
                'company_name' => $invitation->company->name,
                'url' => route(CompanyUser::memberRoute('invitation.show'), ['token' => $token]),
                'expires_at' => $invitation->expires_at->format('Y年n月j日 H:i'),
            ]));
        } catch (\Throwable $e) {
            // メールアドレスは個人情報なので、ログには出さない
            Log::error('CompanyInvitationManager: 招待のメールの送信に失敗しました。', [
                'company_id' => $invitation->company_id,
                'invitation_id' => $invitation->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
