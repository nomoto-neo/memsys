<?php

namespace Tests\Feature;

use App\Enums\StaffAcl;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Neobit\Core\Models\Code;
use Tests\TestCase;

/**
 * 項目見出し一覧（Neobit\Core\Http\Controllers\CodeTableController）。DBで管理するコード表を、
 * 管理画面からまとめて書き換える。編集できるコード表は、config('neobit.code_tables')に載せたもの。
 */
class CodeTableEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 見本のサイトの設定と同じ。性別はコード値を固定、連絡方法は自由に書き換えられる
        config(['neobit.code_tables' => [
            'gender' => ['label' => '性別', 'fixed' => true],
            'contact' => ['label' => '連絡方法', 'fixed' => false],
        ]]);

        foreach (['gender' => ['1' => '男性', '2' => '女性'], 'contact' => ['1' => 'LINE', '2' => '電話']] as $type => $rows) {
            $order = 0;
            foreach ($rows as $code => $name) {
                Code::create(['type' => $type, 'code' => $code, 'name' => $name, 'sort_order' => $order++]);
            }
        }
    }

    private function staff(StaffAcl $acl): Staff
    {
        return Staff::create([
            'name' => '管理 花子',
            'login_id' => 'hanako',
            'email' => 'hanako@example.com',
            'password' => Hash::make('staff-password'),
            'acl' => $acl,
        ]);
    }

    public function test_index_shows_the_rows_of_the_selected_table(): void
    {
        $this->actingAs($this->staff(StaffAcl::Manager), 'admin')
            ->get(route('admin.codes.index', ['type' => 'contact']))
            ->assertOk()
            ->assertSee('<option value="contact" selected>連絡方法</option>', false)
            ->assertSee('value="LINE"', false)
            // 更新の送り先は、アプリが付けた名前の組から作る
            ->assertSee('action="'.route('admin.codes.update').'"', false);
    }

    public function test_update_saves_the_rows_in_the_order_on_the_screen(): void
    {
        $staff = $this->staff(StaffAcl::Manager);

        $this->actingAs($staff, 'admin')
            ->patch(route('admin.codes.update'), [
                'type' => 'contact',
                'codes' => [
                    ['code' => '3', 'name' => 'メール'],
                    ['code' => '1', 'name' => 'LINE'],
                    // コード値が空欄の行は保存しない
                    ['code' => '', 'name' => '消す行'],
                ],
            ])
            ->assertRedirect(route('admin.codes.index', ['type' => 'contact']))
            ->assertSessionHas('status', '「連絡方法」を更新しました。');

        $this->assertSame([3 => 'メール', 1 => 'LINE'], code_table('contact'));
        $this->assertSame([$staff->id], Code::where('type', 'contact')->distinct()->pluck('staff_id')->all());
    }

    public function test_duplicate_code_is_rejected(): void
    {
        // 数字は前ゼロを除いて比べるので、01と1は重複になる
        $this->actingAs($this->staff(StaffAcl::Manager), 'admin')
            ->patch(route('admin.codes.update'), [
                'type' => 'contact',
                'codes' => [['code' => '1', 'name' => 'LINE'], ['code' => '01', 'name' => 'LINE2']],
            ])
            ->assertSessionHasErrors('codes.1.code');

        $this->assertSame(2, Code::where('type', 'contact')->count());
    }

    public function test_fixed_table_cannot_change_its_codes(): void
    {
        $manager = $this->staff(StaffAcl::Manager);

        // コード値を足すとエラー
        $this->actingAs($manager, 'admin')
            ->patch(route('admin.codes.update'), [
                'type' => 'gender',
                'codes' => [['code' => '1', 'name' => '男性'], ['code' => '2', 'name' => '女性'], ['code' => '3', 'name' => 'その他']],
            ])
            ->assertSessionHasErrors('codes');

        // 並び替えと表示名の変更はできる
        $this->actingAs($manager, 'admin')
            ->patch(route('admin.codes.update'), [
                'type' => 'gender',
                'codes' => [['code' => '2', 'name' => '女'], ['code' => '1', 'name' => '男']],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame([2 => '女', 1 => '男'], code_table('gender'));
    }

    public function test_table_not_in_the_config_cannot_be_edited(): void
    {
        $this->actingAs($this->staff(StaffAcl::Manager), 'admin')
            ->patch(route('admin.codes.update'), ['type' => 'prefectures', 'codes' => []])
            ->assertSessionHasErrors('type');
    }

    public function test_staff_who_is_not_a_manager_is_sent_away(): void
    {
        $staff = $this->staff(StaffAcl::Staff);

        $this->actingAs($staff, 'admin')
            ->get(route('admin.codes.index'))
            ->assertRedirect(route('admin.staff.edit', $staff));
    }
}
