<?php

namespace Tests\Feature;

use App\Enums\AppOperationLogAction;
use App\Enums\StaffAcl;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Neobit\Core\Code\CodeTableException;
use Neobit\Core\Enums\OperationLogAction;
use Tests\TestCase;

/**
 * プログラムで作るコード表（config('neobit.code_table_method')）。書いた静的メソッドが返す
 * 「値 => 名称」の配列を、ほかのコード表と同じヘルパーで引ける。
 * コード表は1リクエストの中で使い回すので、テストごとに違うコード名を使う。
 */
class CodeTableMethodTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $loginId, string $name): Staff
    {
        return Staff::create([
            'name' => $name,
            'login_id' => $loginId,
            'email' => $loginId.'@example.com',
            'password' => Hash::make('staff-password'),
            'acl' => StaffAcl::Staff,
        ]);
    }

    public function test_staff_tables_are_made_by_the_methods_of_the_model(): void
    {
        $hanako = $this->staff('hanako', '管理 花子');
        $taro = $this->staff('taro', '管理 太郎');
        $taro->delete();

        // 名前を出す用は、削除したスタッフも含む
        $this->assertSame([$hanako->id => '管理 花子', $taro->id => '管理 太郎'], code_table('staff'));
        $this->assertSame('管理 太郎', code_label('staff', $taro->id));

        // 選ぶ用は、今いるスタッフだけ
        $this->assertSame([$hanako->id => '管理 花子'], code_table('active_staff'));
    }

    public function test_operation_log_action_table_has_the_actions_of_the_framework_and_the_site(): void
    {
        // 設定に書かなくても、フレームワークがoperation_log_actionsの列挙型から作る
        $actions = code_table('operation_log_action');

        $this->assertSame(OperationLogAction::Create->label(), $actions[OperationLogAction::Create->value]);
        $this->assertSame(AppOperationLogAction::BulkMailSend->label(), $actions[AppOperationLogAction::BulkMailSend->value]);
    }

    public function test_code_name_in_two_sources_is_rejected(): void
    {
        config([
            'neobit.code_table_db' => ['_test_both' => ['label' => '重なり', 'fixed' => false]],
            'neobit.code_table_method' => ['_test_both' => [Staff::class, 'nameOptions']],
        ]);

        $this->expectException(CodeTableException::class);
        $this->expectExceptionMessage('どれか1つにしてください');

        code_table('_test_both');
    }

    public function test_method_that_cannot_be_called_is_rejected(): void
    {
        config(['neobit.code_table_method' => ['_test_missing' => [Staff::class, 'noSuchOptions']]]);

        $this->expectException(CodeTableException::class);
        $this->expectExceptionMessage('noSuchOptions');

        code_table('_test_missing');
    }
}
