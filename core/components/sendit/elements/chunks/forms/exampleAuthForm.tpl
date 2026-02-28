<form data-si-form="authForm" data-si-preset="auth">
    <input type="hidden" name="errorLogin">
    <p>Форма авторизации.</p>
    <label>
        <input type="text" name="email" placeholder="Email">
        <p data-si-error="email"></p>
    </label>
    <label>
        <input type="password" name="password" placeholder="Введите пароль">
        <p data-si-error="password"></p>
    </label>
    <div>
        <button type="submit">Отправить</button>
    </div>
</form>
