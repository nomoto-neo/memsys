<?php

namespace Neobit\Core\Seo;

use Illuminate\Database\Eloquent\Model;

/**
 * 記事ごとのSEOの欄（タイトル・説明文・シェアの画像のURL・検索に出さない）を、登録・編集の画面で受け取って保存する。
 * コントローラーに付け、rules()にseoRules()を+で足す。モデルにはHasSeoMetaを付ける。
 * 画面には、入力の部品neobit::_seo_fieldsを置く。
 *
 * FormFlowを使っていれば、値の組み立て・保存・削除はFormFlowがここを呼ぶ。保存はSEOの欄の値が届いたときだけで、
 * 全部空なら行を消す。CSV取り込みでは保存しない。SEOの欄の変更は、操作ログの変わった列には入らない。
 */
trait SeoFields
{
    // ---- 画面から送る名前と、上限 ----

    /** 画面から送る名前 => SEOの欄の列 */
    private static array $seoFieldColumns = [
        'seo_title' => 'title',
        'seo_description' => 'description',
        'seo_image' => 'image',
        'seo_noindex' => 'noindex',
    ];

    /** SEOの欄の検証ルール。コントローラーのrules()に+で足す */
    public function seoRules(): array
    {
        return [
            'seo_title' => ['nullable', 'string', 'max:60'],
            'seo_description' => ['nullable', 'string', 'max:300'],
            'seo_image' => ['nullable', 'string', 'max:255', 'regex:'.Seo::IMAGE_URL_PATTERN],
            'seo_noindex' => ['nullable', 'in:0,1'],
        ];
    }

    /**
     * 画面へ渡す$inputのうち、SEOの欄の分。$sourceにSEOの欄の値があれば（確認画面から戻ったとき・入力エラーのとき・
     * 確認画面）それを、無ければ記事の今のSEOの欄を使う。新規登録では空
     */
    private function seoFieldValues(?Model $model, array $source = []): array
    {
        if (array_intersect_key($source, self::$seoFieldColumns) !== []) {
            return array_intersect_key($source, self::$seoFieldColumns);
        }

        $meta = $model?->exists ? $model->seoMeta : null;

        return [
            'seo_title' => $meta?->title,
            'seo_description' => $meta?->description,
            'seo_image' => $meta?->image,
            'seo_noindex' => $meta?->noindex ? '1' : '0',
        ];
    }

    /**
     * SEOの欄を保存する。FormFlowが本体の保存の後に、同じトランザクションの中で呼ぶ。
     * SEOの欄の値が届いていなければ（部品を置いていない画面）、触らない。全部空なら行を消す。CSV取り込みでは何もしない
     */
    private function saveSeoFields(Model $model, array $validated, bool $fromImport = false): void
    {
        if ($fromImport || array_intersect_key($validated, self::$seoFieldColumns) === []) {
            return;
        }

        $values = [
            'title' => ($validated['seo_title'] ?? null) ?: null,
            'description' => ($validated['seo_description'] ?? null) ?: null,
            'image' => ($validated['seo_image'] ?? null) ?: null,
            'noindex' => ($validated['seo_noindex'] ?? null) == '1',
        ];

        // 全部空なら、行を持たない（自動の値になる）
        if (array_filter($values) === []) {
            $model->seoMeta()->delete();

            return;
        }

        $model->seoMeta()->updateOrCreate([], $values);
    }

    /** 記事を消す前に、SEOの欄の行を消す。FormFlowの削除が呼ぶ */
    private function deleteSeoFields(Model $model): void
    {
        $model->seoMeta()->delete();
    }
}
