<?php

namespace Tests\Feature;

use App\Enums\NoticeMail;
use App\Enums\StaffAcl;
use App\Models\Member;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 画面を短く書くためのヘルパー（hit()・code_options()・code_labels()）と、
 * 画面に渡す$input・$filtersに、値の無い項目のキーも入っていること。
 */
class FormHelpersTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): Staff
    {
        return Staff::create([
            'name' => '管理 花子',
            'login_id' => 'hanako',
            'email' => 'hanako@example.com',
            'password' => Hash::make('staff-password'),
            'acl' => StaffAcl::Manager,
        ]);
    }

    // ---- hit() ----

    public function test_hit_compares_a_single_value(): void
    {
        // フォームから届いた文字と、コード表の数値は、同じ値として扱う
        $this->assertTrue(hit('13', 13));
        $this->assertTrue(hit(13, '13'));
        $this->assertTrue(hit(0, '0'));
        $this->assertTrue(hit(NoticeMail::Stop, 0));
        $this->assertFalse(hit('13', 1));
        $this->assertFalse(hit('1', 13));
    }

    public function test_hit_looks_into_an_array(): void
    {
        $this->assertTrue(hit(['1', '13'], 13));
        $this->assertTrue(hit([1, 13], '13'));
        $this->assertFalse(hit(['1', '13'], 3));
        $this->assertFalse(hit([], 3));
    }

    public function test_hit_is_false_for_an_empty_value(): void
    {
        $this->assertFalse(hit(null, 0));
        $this->assertFalse(hit('', 0));
        $this->assertFalse(hit(null, ''));
        $this->assertFalse(hit('', ''));
    }

    // ---- code_options()・code_labels() ----

    public function test_code_options_marks_the_selected_one(): void
    {
        $html = (string) code_options('notice_mail', '0');

        $this->assertSame(
            "<option value=\"1\">受け取る</option>\n<option value=\"0\" selected>受け取らない</option>",
            $html,
        );

        // 選ばれていなければ、どれにも付かない。複数選べるプルダウンは配列で渡す
        $this->assertStringNotContainsString('selected', (string) code_options('notice_mail', null));
        $this->assertSame(2, substr_count((string) code_options('notice_mail', [0, 1]), ' selected'));
    }

    public function test_code_options_groups_a_nested_array(): void
    {
        $html = (string) code_options([
            '東北' => [2 => '青森県', 3 => '岩手県'],
            '関東' => [13 => '東京都'],
            99 => 'その他',
        ], '3');

        $this->assertSame(implode("\n", [
            '<optgroup label="東北">',
            '<option value="2">青森県</option>',
            '<option value="3" selected>岩手県</option>',
            '</optgroup>',
            '<optgroup label="関東">',
            '<option value="13">東京都</option>',
            '</optgroup>',
            '<option value="99">その他</option>',
        ]), $html);
    }

    public function test_csv_with_headings_is_read_as_two_levels(): void
    {
        // テストの間だけ、2階層のコード表を置く
        $path = base_path('code/_test_pref_area.csv');
        file_put_contents($path, "# 地域ごとの都道府県\n99,その他\n[東北]\n02,青森県\n3,岩手県\n\n[関東]\n13,東京都\n");

        try {
            $this->assertSame([
                99 => 'その他',
                '東北' => [2 => '青森県', 3 => '岩手県'],
                '関東' => [13 => '東京都'],
            ], code_table('_test_pref_area'));

            // プルダウンは、名前を渡すだけで<optgroup>になる
            $html = (string) code_options('_test_pref_area', 13);
            $this->assertStringContainsString('<optgroup label="関東">'."\n".'<option value="13" selected>東京都</option>', $html);

            // 見出しは名称ではないので、名称としては引けない
            $this->assertSame('', code_label('_test_pref_area', '東北'));
            $this->assertSame('その他', code_label('_test_pref_area', 99));
        } finally {
            unlink($path);
        }
    }

    public function test_code_labels_joins_the_names(): void
    {
        $this->assertSame('北海道、東京都', code_labels('prefectures', ['1', 13]));
        $this->assertSame('北海道/東京都', code_labels('prefectures', [1, 13], '/'));

        // コード表に無い値は飛ばす。1つだけの値と、空の値も受け付ける
        $this->assertSame('東京都', code_labels('prefectures', [999, 13]));
        $this->assertSame('東京都', code_labels('prefectures', 13));
        $this->assertSame('', code_labels('prefectures', null));
    }

    // ---- 値の無い項目のキー ----

    public function test_input_has_every_field_of_the_rules(): void
    {
        $member = Member::factory()->create();

        // 編集の画面。パスワードは、モデルの値からは作らない項目
        $input = $this->actingAs($this->manager(), 'admin')
            ->get(route('admin.members.edit', $member))
            ->assertOk()
            ->viewData('input');

        $this->assertArrayHasKey('password', $input);
        $this->assertNull($input['password']);
        $this->assertSame($member->name, $input['name']);
    }

    public function test_filters_have_every_search_field(): void
    {
        $filters = $this->actingAs($this->manager(), 'admin')
            ->get(route('admin.members.index'))
            ->assertOk()
            ->viewData('filters');

        foreach (['q', 'orderby', 'email', 'phone', 'prefecture', 'notice_mail'] as $key) {
            $this->assertArrayHasKey($key, $filters);
        }
    }
}
