<form data-si-form="logoutForm" data-si-preset="logout">
    <input type="hidden" name="errorLogout">
    <button type="submit">Выйти</button>
</form>

<form data-si-form="dataForm" data-si-preset="dataedit">
    <p>Форма изменения личных данных.</p>
    <label>
        <input type="text" name="fullname" value="{$_modx->user.fullname}" placeholder="Полное имя">
        <p data-si-error="fullname"></p>
    </label>
    <label>
        <input type="text" name="email" value="{$_modx->user.email}" placeholder="Email">
        <p data-si-error="email"></p>
    </label>
    <label>
        <input type="tel" name="phone" value="{$_modx->user.phone}" placeholder="+7(">
        <p data-si-error="phone"></p>
    </label>
    <label>
        <input type="text" name="extended[inn]" value="{$_modx->user.extended['inn']}" placeholder="ИНН">
        <p data-si-error="extended[inn]"></p>
    </label>
    <div>
        <button type="submit">Сохранить</button>
    </div>
</form>

<form data-si-form="editPassForm" data-si-preset="editpass">
    <p>Изменить пароль.</p>
    <label>
        <input type="password" name="password" placeholder="Введите пароль">
        <p data-si-error="password"></p>
    </label>
    <label>
        <input type="password" name="password_confirm" placeholder="Подтвердите пароль">
        <p data-si-error="password_confirm"></p>
    </label>
    <div>
        <button type="submit">Изменить</button>
    </div>
</form>
