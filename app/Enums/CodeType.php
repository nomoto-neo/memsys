<?php

namespace App\Enums;

/**
 * 管理画面の項目見出し一覧で編集できる、DBのコード表の一覧。値がコード名になる。
 *
 * ここに載せたコード表はt_codesテーブルに保存し、code_table('gender')のように読み出す。
 * CSVのコード表と同じく、選択肢として並べるだけのものに使い、管理画面から書き換えられる。
 * コード表を増やすときは、caseとSETTINGSに1行ずつ足し、初期データはCodeSeederに書く。
 * この列挙型自体も、項目見出し一覧のプルダウンとして、code_table('code_type')で使える。
 */
enum CodeType: string implements CodeTableEnum
{
    case Gender = 'gender';
    case Contact = 'contact';

    /**
     * コード表ごとの設定。
     * - label：項目見出し一覧のプルダウンに出す名前
     * - fixed：コード値を固定するか。trueなら、並び替えと表示名の変更だけができる。
     *   プログラムが特定のコード値を前提に動くコード表は、trueにする。たとえば、性別の
     *   値でメールの文面を変えているなら、値を消したり変えたりすると動かなくなるため
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
