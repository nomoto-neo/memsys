<?php

namespace Tests\Feature;

use App\Enums\StaffAcl;
use App\Models\Member;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Neobit\Core\Code\CodeTable;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Models\CsvDownloadLog;
use Neobit\Core\Models\OperationLog;
use Tests\TestCase;

/**
 * 操作ログの画面（Neobit\Core\Http\Controllers\OperationLogController）。フレームワークの画面で、
 * ルートの名前はアプリが付ける。種類の名前は、config('neobit.operation_log_models')の列挙型から出す。
 */
class OperationLogScreenTest extends TestCase
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

    public function test_list_shows_how_long_logs_are_kept(): void
    {
        $manager = $this->manager();

        $this->actingAs($manager, 'admin')->get(route('admin.operation-logs.index'))
            ->assertSee('操作ログは365日残し、過ぎたものは自動で消えます。');

        // 0なら操作ログを取らない
        config(['cleanup.operation_log_days' => 0]);
        $this->actingAs($manager, 'admin')->get(route('admin.operation-logs.index'))
            ->assertSee('操作ログは取っていません。それまでに残したものは消えずに残ります。');
    }

    public function test_list_shows_model_names_and_related_record_details(): void
    {
        $manager = $this->manager();

        // CSVのダウンロード。内訳は、記録が返す項目を並べる
        $csv = CsvDownloadLog::create(['name' => '会員一覧', 'operator_id' => $manager->id, 'row_count' => 12, 'conditions' => ['prefecture' => [13, 14]]]);
        OperationLog::create([
            'operator_type' => 'staff',
            'operator_id' => $manager->id,
            'action' => OperationLogAction::CsvDownload,
            'detail' => ['name' => '会員一覧', 'csv_download_log_id' => $csv->id],
        ]);

        $this->actingAs($manager, 'admin')
            ->get(route('admin.operation-logs.index'))
            ->assertOk()
            // 種類の名前（スタッフ）と、今の氏名
            ->assertSee('スタッフID:'.$manager->id.'　管理 花子')
            // 内訳の窓
            ->assertSee('12件')
            ->assertSee('prefecture：13, 14')
            // アプリが付けたルートの名前の組から、送り先を作る
            ->assertSee('action="'.route('admin.operation-logs.search').'"', false)
            ->assertSee(route('admin.operation-logs.csv'));
    }

    public function test_model_without_a_name_shows_its_type(): void
    {
        // 設定に列挙型が無くても、例外にせず、種類の名前のまま出す。コード表は1つのプロセスの中で
        // 使い回すので、前のテストで作った一覧を消してから確かめる
        config(['neobit.operation_log_models' => []]);
        (new \ReflectionProperty(CodeTable::class, 'cache'))->setValue(null, []);
        $manager = $this->manager();
        OperationLog::create(['operator_type' => 'staff', 'operator_id' => $manager->id, 'action' => OperationLogAction::Login]);

        $this->actingAs($manager, 'admin')
            ->get(route('admin.operation-logs.index'))
            ->assertOk()
            ->assertSee('staffID:'.$manager->id);

        // 空の一覧を、後のテストに残さない
        (new \ReflectionProperty(CodeTable::class, 'cache'))->setValue(null, []);
    }

    public function test_csv_and_member_page_work(): void
    {
        $manager = $this->manager();
        $member = Member::create(['name' => '会員 太郎', 'email' => 'taro@example.com', 'password' => Hash::make('member-password')]);
        OperationLog::create(['operator_type' => 'member', 'operator_id' => $member->id, 'action' => OperationLogAction::Login]);

        $csv = $this->actingAs($manager, 'admin')->get(route('admin.operation-logs.csv'));
        $csv->assertOk();
        $this->assertStringContainsString('会員', $csv->streamedContent());

        // 会員ごとの操作ログは、フレームワークの表の部分を使う
        $this->actingAs($manager, 'admin')
            ->get(route('admin.members.operation-logs', $member))
            ->assertOk()
            ->assertSee('会員ID:'.$member->id.'　会員 太郎');
    }
}
