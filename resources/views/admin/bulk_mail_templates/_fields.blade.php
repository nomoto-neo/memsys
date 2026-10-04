{{--
    一斉メールの文面の入力欄。新規登録と編集の2画面から呼ぶ。
    $inputは画面に出す値、$requiredは必須の印で、どちらもコントローラーが作る。
    件名と本文の{{$name}}は、送るときに宛先の氏名に置き換わる。
--}}
<div class="mb-3">
    <label for="title" class="form-label">タイトル（管理用） {!! $required['title'] ?? '' !!}</label>
    <input id="title" type="text" name="title" maxlength="100"
           class="form-control"
           value="{{ $input['title'] ?? '' }}">
    <div class="form-text">一覧で見分けるための名前です。メールには出ません。</div>
    <div class="invalid-feedback" data-item="title">{{ $errors->first('title') }}</div>
</div>

<div class="mb-3">
    <label for="subject" class="form-label">件名 {!! $required['subject'] ?? '' !!}</label>
    <input id="subject" type="text" name="subject" maxlength="200"
           class="form-control"
           value="{{ $input['subject'] ?? '' }}">
    <div class="invalid-feedback" data-item="subject">{{ $errors->first('subject') }}</div>
</div>

<div class="mb-3">
    <label for="body" class="form-label">本文 {!! $required['body'] ?? '' !!}</label>
    <textarea id="body" name="body" rows="15" class="form-control">{{ $input['body'] ?? '' }}</textarea>
    <div class="form-text">@{{$name}} と書いたところに、宛先の氏名が入ります。件名にも使えます。</div>
    <div class="invalid-feedback" data-item="body">{{ $errors->first('body') }}</div>
</div>
