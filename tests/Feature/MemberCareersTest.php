<?php

namespace Tests\Feature;

use App\Enums\StaffAcl;
use App\Models\Member;
use App\Models\MemberCareer;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 会員の経歴（学歴・職歴）。子テーブル（Neobit\Core\Crud\ChildTables）の、最初に並べる枠の数（initial）の見本。
 * 管理画面とマイページで入力し、履歴書のPDFの学歴・職歴の欄に出る。
 */
class MemberCareersTest extends TestCase
{
    use RefreshDatabase;

    private function member(): Member
    {
        return Member::factory()->create(['email' => 'taro@example.com']);
    }

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

    /** 会員の今の値の入力。経歴の組は$careersで足す */
    private function profileOf(Member $member, array $careers): array
    {
        return [
            'name' => $member->name,
            'kana' => $member->kana,
            'email' => $member->email,
            'phone' => $member->phone,
            'birthdate' => $member->birthdate?->format('Y-m-d'),
            'prefecture' => $member->prefecture,
            'notice_mail' => $member->notice_mail,
            'careers_group' => '1',
        ] + $careers;
    }

    /**
     * 経歴の組の列ごとの配列。1行は[年, 月, 内容]
     *
     * @param  list<array{0: mixed, 1: mixed, 2: mixed}>  $rows
     */
    private function careerRows(array $rows): array
    {
        return [
            'careers_id' => array_fill(0, count($rows), ''),
            'careers_year' => array_map(fn ($row) => (string) $row[0], $rows),
            'careers_month' => array_map(fn ($row) => (string) $row[1], $rows),
            'careers_content' => array_map(fn ($row) => (string) $row[2], $rows),
        ];
    }

    public function test_edit_screens_show_ten_slots_and_the_add_button(): void
    {
        $member = $this->member();
        $member->careers()->create(['year' => 2020, 'month' => 4, 'content' => '株式会社〇〇 入社']);

        // 1行と空の9枠で10枠、行のひな型が1つ。20枠まで足せる
        $html = $this->actingAs($this->manager(), 'admin')->get(route('admin.members.edit', $member))->assertOk()->getContent();
        $this->assertSame(11, substr_count($html, 'name="careers_id[]"'));
        $this->assertStringContainsString('data-group="careers" data-max="20">', $html);
        $this->assertStringContainsString('経歴を追加', $html);

        $html = $this->actingAs($member, 'web')->get(route('mypage.edit'))->assertOk()->getContent();
        $this->assertSame(11, substr_count($html, 'name="careers_id[]"'));
        $this->assertStringContainsString('value="株式会社〇〇 入社"', $html);
    }

    public function test_mypage_saves_the_filled_rows_in_screen_order(): void
    {
        $member = $this->member();

        // 間の空の枠は除き、詰めて保存する
        $this->actingAs($member, 'web')
            ->patch(route('mypage.update'), $this->profileOf($member, $this->careerRows([
                [2016, 4, '〇〇大学 入学'],
                ['', '', ''],
                [2020, 3, '〇〇大学 卒業'],
            ])))
            ->assertRedirect(route('mypage'));

        $this->assertSame(
            [[2016, 4, '〇〇大学 入学'], [2020, 3, '〇〇大学 卒業']],
            $member->careers()->get()->map(fn ($c) => [$c->year, $c->month, $c->content])->all()
        );

        // マイページに出る
        $this->actingAs($member, 'web')->get(route('mypage'))->assertSee('2016年4月　〇〇大学 入学');
    }

    public function test_partly_filled_row_and_out_of_range_year_are_rejected(): void
    {
        $member = $this->member();

        // 年だけの行は、月と内容も要る。年は1900から今年まで
        $this->actingAs($member, 'web')
            ->from(route('mypage.edit'))
            ->patch(route('mypage.update'), $this->profileOf($member, $this->careerRows([
                [2020, '', ''],
                [1899, 4, '古すぎる年'],
                [now()->year + 1, 4, '来年'],
            ])))
            ->assertSessionHasErrors(['careers_month.0', 'careers_content.0', 'careers_year.1', 'careers_year.2']);

        $this->assertSame(0, MemberCareer::count());
    }

    public function test_admin_saves_through_the_confirm_screen_and_shows_the_rows(): void
    {
        $member = $this->member();
        $manager = $this->manager();
        $input = $this->profileOf($member, $this->careerRows([[2020, 4, '株式会社〇〇 入社']]));

        // 確認画面に、入っている行だけが出る
        $this->actingAs($manager, 'admin')
            ->patch(route('admin.members.confirm.update', $member), $input)
            ->assertOk()
            ->assertSee('2020年4月　株式会社〇〇 入社');

        $this->actingAs($manager, 'admin')->patch(route('admin.members.update', $member), $input)->assertRedirect();
        $this->assertSame(['株式会社〇〇 入社'], $member->careers()->pluck('content')->all());

        // 詳細の画面にも出る
        $this->actingAs($manager, 'admin')->get(route('admin.members.show', $member))->assertSee('2020年4月　株式会社〇〇 入社');
    }

    public function test_more_than_twenty_rows_are_rejected(): void
    {
        $member = $this->member();
        $rows = array_fill(0, 21, [2020, 4, '内容']);

        $this->actingAs($member, 'web')
            ->from(route('mypage.edit'))
            ->patch(route('mypage.update'), $this->profileOf($member, $this->careerRows($rows)))
            ->assertSessionHasErrors('careers_id');
    }

    public function test_resume_pdfs_show_the_careers(): void
    {
        $member = $this->member();
        $member->careers()->createMany([
            ['year' => 2016, 'month' => 4, 'content' => '〇〇大学 入学'],
            ['year' => 2020, 'month' => 4, 'content' => '株式会社〇〇 入社'],
        ]);

        // HTMLから作る履歴書と、定型紙に載せる履歴書の、どちらの画面にも経歴が出る
        $html = view('pdf.resume', ['member' => $member, 'images' => []])->render();
        $this->assertStringContainsString('〇〇大学 入学', $html);
        $this->assertStringContainsString('株式会社〇〇 入社', $html);

        $onSheet = view('pdf.resume_on_sheet', ['member' => $member, 'images' => []])->render();
        $this->assertStringContainsString('株式会社〇〇 入社', $onSheet);
    }

    public function test_withdraw_deletes_the_careers(): void
    {
        $member = $this->member();
        $member->careers()->create(['year' => 2020, 'month' => 4, 'content' => '株式会社〇〇 入社']);

        $this->actingAs($member, 'web')->delete(route('mypage.destroy'))->assertRedirect('/');

        $this->assertSame(0, MemberCareer::count());
    }
}
