<form enctype="multipart/form-data" data-si-form="formWithDragFile" data-si-preset="form_with_file">
    <p>Форма для отправки файла с возможностью перетаскивать файлы в область загрузки.</p>
    <label>
        <input type="text" name="name" placeholder="Полное имя">
        <p data-si-error="name"></p>
    </label>
    <div data-fu-wrap data-si-preset="upload_drop_file" data-si-nosave>
        <div data-fu-progress=""></div>
        <input type="hidden" name="filelist" data-fu-list>
        <label data-fu-dropzone>
            <input type="file" name="files" data-fu-field multiple>
            <span data-fu-hide>Перетащите сюда файлы</span>
        </label>
        <template data-fu-tpl>
            <button type="button" data-fu-path="$path">$filename&nbsp;x</button>
        </template>
    </div>
    <div>
        <button type="submit">Отправить</button>
    </div>
</form>
