<?php

namespace App\Enums;

/**
 * 管理画面の「項目見出し一覧」（Admin\CodeController）で編集できるコード表の一覧。
 * 値（'gender'など）がコード名、label()がプルダウンに出す名前になる。
 *
 * ここに載せたコード表は、t_codesテーブルに保存され、code_table('gender')などで
 * 読み出せる（App\Support\CodeTable参照）。code/*.csvのコード表と同じく、選択肢と
 * して並べるだけのものに使い、管理画面から値や名前を書き換えられる。
 * コード表を増やすときは、caseとSETTINGSに1行ずつ足し、必要なら初期データを
 * シーダー（CodeSeeder）に書く。同じコード名の列挙型・CSVがあってはいけない。
 *
 * この列挙型自体も、コード表code_table('code_type')として使える
 * （項目見出し一覧のプルダウン。並び順はcaseの順）。
 */
enum CodeType: string implements CodeTableEnum
{
    case Gender = 'gender';
    case Contact = 'contact';

    /**
     * コード表ごとの設定。
     * - label：項目見出し一覧のプルダウンに出す名前
     * - fixed：コード値を固定するか。trueのコード表は、並び替えと表示名の変更だけが
     *   でき、コード値の変更・行の追加・削除はできない。プログラムが特定のコード値を
     *   前提に処理している（例：性別が「1」か「2」かでメールの文面を変える）コード表は
     *   trueにする。コード値を消したり変えたりすると、その処理が動かなくなるため
     */
    private const SETTINGS = [
        self::Gender->value => ['label' => '性別', 'fixed' => true],
        self::Contact->value => ['label' => '連絡方法', 'fixed' => false],
    ];

    public function label(): string
    {
        return self::SETTINGS[$this->value]['label'];
    }

    /** コード値を固定するか（SETTINGSのfixed）。 */
    public function isFixed(): bool
    {
        return self::SETTINGS[$this->value]['fixed'];
    }
}
