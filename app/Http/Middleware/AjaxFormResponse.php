<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpFoundation\Response;

/**
 * 画面を読み直さずに送るフォーム（クラス名ajax_submitの付いたフォーム。resources/js/app.js）への返事を、
 * JavaScriptが受け取れる形に読み替える。
 *
 * モーダルの中のフォームは、エラーで画面を読み直すとモーダルが閉じてしまい、エラーが見えない。
 * そこでフォームをAjaxで送り、エラーはモーダルの中に出す。コントローラーは、ふつうの送信と
 * 同じくリダイレクトを返せばよい。Ajaxのときだけ、ここでJSONに読み替える。
 * - エラー付きのリダイレクト   422と、項目ごとのエラーの文言。{"errors": {"code": "…"}}
 * - それ以外のリダイレクト     200と、移動先のURL。{"redirect": "…"}。JavaScriptが、その画面へ移す
 *
 * 移動先へは、ブラウザが改めて開きに行く。そのため、コントローラーがセッションに仮置きした
 * 値（完了のメッセージなど）は、移動先の画面でそのまま使える。
 * validate()で止まったときは、Laravelが自分で422のJSONを返すので、ここでは触らない。
 */
class AjaxFormResponse
{
    /** ajax_submitのフォームからの送信に、JavaScriptが付けるヘッダーの名前 */
    public const HEADER = 'X-Ajax-Form';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 読み替えるのは、ajax_submitのフォームからの送信への、リダイレクトだけ
        if (! $request->hasHeader(self::HEADER) || ! $response instanceof RedirectResponse) {
            return $response;
        }

        // エラー付きのリダイレクト。エラーは、次の画面のためにセッションへ仮置きされている
        $errors = $request->session()->get('errors');

        // エラーの入れ物（バッグ）は、フォームごとに分けてあることがある。送ったフォームの分だけが
        // 入っているので、入れ物の区別は捨てて、項目ごとに最初の1件を集める。
        // ViewErrorBag::any()は既定の入れ物しか見ないので、全部の入れ物を自分で見る
        $messages = [];
        if ($errors instanceof ViewErrorBag) {
            foreach ($errors->getBags() as $bag) {
                foreach ($bag->messages() as $field => $list) {
                    $messages[$field] = $list[0];
                }
            }
        }

        if ($messages !== []) {
            // 画面を読み直さないので、仮置きは使われない。残すと、次に開いた画面にエラーが出てしまう
            $request->session()->forget(['errors', '_old_input']);

            return response()->json(['errors' => $messages], 422);
        }

        return response()->json(['redirect' => $response->getTargetUrl()]);
    }
}
