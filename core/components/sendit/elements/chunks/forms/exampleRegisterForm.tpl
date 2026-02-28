<form data-si-form="regForm" data-si-preset="register">
    <p>Форма регистрации.</p>
    <label>
        <input type="text" name="fullname" placeholder="Полное имя">
        <p data-si-error="fullname"></p>
    </label>
    <label>
        <input type="text" name="email" placeholder="Email">
        <p data-si-error="email"></p>
    </label>
    <label>
        <input type="password" name="password" placeholder="Введите пароль">
        <p data-si-error="password"></p>
    </label>
    <label>
        <input type="password" name="password_confirm" placeholder="Подтвердите пароль">
        <p data-si-error="password_confirm"></p>
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
