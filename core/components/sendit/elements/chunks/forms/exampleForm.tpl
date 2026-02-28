<form data-si-form="simpleForm" data-si-preset="simpleform">
    <p>
        Обычная форма. Поля &#171;Полное имя&#187; и &#171;Email&#187; обязательны, к тому же проверяется корректность ввода адреса электронной почты.
        Также есть проверка обязательности заполнения чекбокса.
    </p>
    <label>
        <input type="text" name="name" placeholder="Полное имя">
        <p data-si-error="name"></p>
    </label>
    <label>
        <input type="text" name="email" placeholder="Email">
        <p data-si-error="email"></p>
    </label>
    <div>
        <input type="checkbox" name="politics" id="politics">
        <label for="politics">Я на всё согласен!</label>
        <p data-si-error="politics"></p>
    </div>
    <div>
        <button type="submit">Отправить</button>
    </div>
</form>
