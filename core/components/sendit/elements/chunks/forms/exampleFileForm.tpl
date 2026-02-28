<form enctype="multipart/form-data" data-si-form="formWithFile" data-si-preset="form_with_file">
    <p>Форма для отправки файла.</p>
    <label>
        <input type="text" name="name" placeholder="Полное имя">
        <p data-si-error="name"></p>
    </label>
    <div data-fu-wrap data-si-preset="upload_simple_file" data-si-nosave>
        <div data-fu-progress=""></div>
        <input type="hidden" name="filelist" data-fu-list>
        <input type="file" name="files" data-fu-field multiple placeholder="Выберите файл">
        <template data-fu-tpl>
            <button type="button" data-fu-path="$path">$filename&nbsp;x</button>
        </template>
    </div>
    <div>
        <button type="submit">Отправить</button>
    </div>
</form>
