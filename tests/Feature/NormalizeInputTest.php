<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\StaffAcl;
use App\Models\BulkMailTemplate;
use App\Models\Category;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Member;
use App\Models\News;
use App\Models\Staff;
use App\Rules\HiraganaRule;
use App\Rules\KatakanaRule;
use App\Support\CsvColumnSet;
use App\Support\CsvImportSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * 入力された文字の、全角と半角の揺らぎをそろえる処理（App\Support\InputNormalizer）。
 * 画面からの入力とCSV取り込みの両方に、検証の前に掛かる。そろえないのは、パスワードと、
 * モデルで指定した項目だけ。ひらがなとカタカナは変換せず、フリガナは検証でエラーにする。
 */
class NormalizeInputTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 届いた入力を、そのまま返すだけのルート。ミドルウェアを通った後の値を見る
        Route::middleware('web')->post('/_test/echo', fn (Request $request) => response()->json($request->all()));
    }

    private function echoBack(array $input): array
    {
        return $this->post('/_test/echo', $input)->assertOk()->json();
    }

    private function manager(): Staff
    {
        return Staff::create([
            'name' => '管理 花子',
            'login_id' => 'hanako',
            'password' => Hash::make('correct-password'),
            'acl' => StaffAcl::Manager,
        ]);
    }

    // BOM付きUTF-8のCSVファイル
    private function csvFile(string $name, array $lines): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "\xEF\xBB\xBF".implode("\r\n", $lines)."\r\n");
    }

    // CSVを確認画面に通してから、取り込みを実行する
    private function importCsv(Staff $staff, string $url, UploadedFile $file): void
    {
        $confirm = $this->actingAs($staff, 'admin')->post("{$url}/confirm", ['csv_file' => $file])->assertOk();
        $token = $confirm->viewData('token');

        $this->assertNotNull($token, 'CSVにエラーがあります：'.json_encode($confirm->viewData('result')->errors, JSON_UNESCAPED_UNICODE));

        $this->actingAs($staff, 'admin')->post("{$url}/execute", ['confirm_token' => $token])->assertSessionMissing('error');
    }

    public function test_full_width_letters_digits_and_symbols_become_half_width(): void
    {
        $result = $this->echoBack([
            'tel' => '０３－１２３４－５６７８',
            'email' => 'ｔａｒｏ＠ｅｘａｍｐｌｅ．ｃｏｍ',
            'url' => 'ｈｔｔｐｓ：／／ｅｘａｍｐｌｅ．ｃｏｍ／',
            'address' => '千代田区千代田１－１　ＡＢＣビル３Ｆ',
        ]);

        $this->assertSame('03-1234-5678', $result['tel']);
        $this->assertSame('taro@example.com', $result['email']);
        $this->assertSame('https://example.com/', $result['url']);
        // 全角の空白は、そのまま残す
        $this->assertSame('千代田区千代田1-1　ABCビル3F', $result['address']);
    }

    public function test_half_width_kana_becomes_full_width_and_hiragana_is_kept(): void
    {
        $result = $this->echoBack(['kana' => 'ｶﾌﾞｼｷｶﾞｲｼｬ ﾊﾟｰﾄﾅｰ', 'name' => 'やまだ たろう']);

        // 濁点と半濁点は、前の文字と1文字にまとめる
        $this->assertSame('カブシキガイシャ パートナー', $result['kana']);
        // ひらがなとカタカナは変換しない
        $this->assertSame('やまだ たろう', $result['name']);
    }

    public function test_text_with_line_breaks_and_long_text_are_normalized_too(): void
    {
        // どの項目がそろえられるかを、値の中身で変えない。改行を含む値も、長い値もそろえる
        $result = $this->echoBack(['memo' => "ＡＢＣについて\n１２３の件", 'long' => str_repeat('１', 1000)]);

        $this->assertSame("ABCについて\n123の件", $result['memo']);
        $this->assertSame(str_repeat('1', 1000), $result['long']);
    }

    public function test_passwords_are_kept_and_arrays_are_normalized_item_by_item(): void
    {
        $result = $this->echoBack([
            'password' => 'ｐａｓｓ１２３',
            'password_confirmation' => 'ｐａｓｓ１２３',
            'current_password' => 'ｏｌｄ１',
            'tags' => ['ＡＢＣ', 'ｲﾍﾞﾝﾄ'],
            'count' => 5,
        ]);

        $this->assertSame(['ｐａｓｓ１２３', 'ｐａｓｓ１２３', 'ｏｌｄ１'], [$result['password'], $result['password_confirmation'], $result['current_password']]);
        $this->assertSame(['ABC', 'イベント'], $result['tags']);
        $this->assertSame(5, $result['count']);
    }

    public function test_full_width_input_passes_validation_and_is_saved_half_width(): void
    {
        $company = Company::create(['code' => 'acme', 'name' => '株式会社acme', 'tel' => '03-0000-0000', 'status' => CompanyStatus::Approved]);
        $user = CompanyUser::create(['company_id' => $company->id, 'login_id' => 'yamada', 'name' => '山田 太郎', 'email' => 'yamada@example.com', 'password' => Hash::make('x')]);

        // 全角で入力した電話番号と郵便番号が、検証でエラーにならず、半角で保存される
        $this->actingAs($user, 'company')->patch('/company/mypage/update', [
            'name' => '株式会社ａｃｍｅ',
            'kana' => 'ｶﾌﾞｼｷｶﾞｲｼｬｱｸﾒ',
            'tel' => '０３－１２３４－５６７８',
            'zip' => '１００－０００１',
            'address' => '千代田区千代田１－１',
        ])->assertSessionHasNoErrors();

        $company->refresh();
        $this->assertSame(
            ['株式会社acme', 'カブシキガイシャアクメ', '03-1234-5678', '100-0001', '千代田区千代田1-1'],
            [$company->name, $company->kana, $company->tel, $company->zip, $company->address],
        );
    }

    public function test_fields_listed_in_the_model_are_kept_as_written(): void
    {
        // 一斉メールの文面は、件名と本文をそろえない（BulkMailTemplate::RAW_INPUT_FIELDS）。管理用の名前はそろえる
        $this->actingAs($this->manager(), 'admin')->post('/admin/bulk-mail-templates', [
            'title' => '１０月のご案内',
            'subject' => '【重要】１０月のご案内',
            'body' => "{{\$name}} 様\nＡＢＣ：１２３",
        ])->assertSessionHasNoErrors();

        $template = BulkMailTemplate::sole();
        $this->assertSame('10月のご案内', $template->title);
        $this->assertSame('【重要】１０月のご案内', $template->subject);
        $this->assertSame("{{\$name}} 様\nＡＢＣ：１２３", str_replace("\r\n", "\n", $template->body));
    }

    public function test_csv_import_is_normalized_like_screen_input(): void
    {
        Storage::fake(CsvImportSettings::TMP_DISK);
        $member = Member::factory()->create(['phone' => null, 'kana' => null]);

        // 全角の電話番号と半角カナのフリガナが、検証でエラーにならず、そろえて保存される
        $this->importCsv($this->manager(), '/admin/members/csv-import', $this->csvFile('members.csv', [
            '会員ID,電話番号,フリガナ',
            "{$member->id},０９０－１２３４－５６７８,ﾔﾏﾀﾞ ﾀﾛｳ",
        ]));

        $member->refresh();
        $this->assertSame(['090-1234-5678', 'ヤマダ タロウ'], [$member->phone, $member->kana]);
    }

    public function test_csv_columns_of_the_fields_listed_in_the_model_are_found(): void
    {
        // CSVを読む側は、そろえない項目に取り込む列を、列の番号で見分ける
        $columns = new CsvColumnSet(['記事ID' => 'id', 'タイトル' => 'title', '本文' => 'body']);
        $mapping = $columns->mapHeadings(['記事ID', '本文', 'タイトル'], ['title', 'body'], 'id');

        $this->assertSame([1 => true], $columns->columnsOfFields($mapping['map'], News::RAW_INPUT_FIELDS));
        $this->assertSame([], $columns->columnsOfFields($mapping['map'], []));
    }

    public function test_csv_import_normalizes_the_other_columns_of_a_model_with_raw_fields(): void
    {
        Storage::fake(CsvImportSettings::TMP_DISK);
        Category::create(['name' => 'イベント', 'display_order' => 0]);

        // ニュースは、本文をそろえない（News::RAW_INPUT_FIELDS）。件名や日付の列は、そろえる
        $this->importCsv($this->manager(), '/admin/news/csv-import', $this->csvFile('news.csv', [
            '記事ID,タイトル,記事日付,状態,公開範囲,カテゴリー',
            ',１０月のイベント,２０２６／１０／０１,表示,一般公開,イベント',
        ]));

        $news = News::sole();
        $this->assertSame('10月のイベント', $news->title);
        $this->assertSame('2026-10-01', $news->article_date->format('Y-m-d'));
    }

    public function test_katakana_rule_rejects_hiragana_letters_digits_and_hyphens(): void
    {
        $passes = fn (string $value) => Validator::make(['kana' => $value], ['kana' => [new KatakanaRule()]])->passes();

        // 長音と中点、全角と半角の空白は通す
        $this->assertTrue($passes('ヤマダ タロウ'));
        $this->assertTrue($passes('カブシキガイシャ　エー・ビー・シー'));
        $this->assertTrue($passes('ヴァイオリン'));

        $this->assertFalse($passes('やまだ たろう'));
        $this->assertFalse($passes('ヤマダ太郎'));
        $this->assertFalse($passes('ヤマダABC'));
        $this->assertFalse($passes('ヤマダ123'));
        // 長音の代わりにハイフンを入れたもの
        $this->assertFalse($passes('パ-トナ-'));
    }

    public function test_hiragana_rule_rejects_katakana_letters_digits_and_hyphens(): void
    {
        $passes = fn (string $value) => Validator::make(['kana' => $value], ['kana' => [new HiraganaRule()]])->passes();

        $this->assertTrue($passes('やまだ たろう'));
        $this->assertTrue($passes('ぱーとなー　えー・びー'));
        $this->assertTrue($passes('ゔぁいおりん'));

        $this->assertFalse($passes('ヤマダ タロウ'));
        $this->assertFalse($passes('やまだ太郎'));
        $this->assertFalse($passes('やまだABC'));
        $this->assertFalse($passes('ぱ-とな-'));
    }

    public function test_hiragana_in_furigana_is_an_error(): void
    {
        $company = Company::create(['code' => 'acme', 'name' => '株式会社acme', 'tel' => '03-0000-0000', 'status' => CompanyStatus::Approved]);
        $user = CompanyUser::create(['company_id' => $company->id, 'login_id' => 'yamada', 'name' => '山田 太郎', 'email' => 'yamada@example.com', 'password' => Hash::make('x')]);

        $this->actingAs($user, 'company')->from('/company/mypage/edit')
            ->patch('/company/mypage/update', ['name' => '株式会社acme', 'kana' => 'かぶしきがいしゃあくめ', 'tel' => '03-1234-5678'])
            ->assertSessionHasErrors('kana');

        $this->assertNull($company->fresh()->kana);
    }
}
