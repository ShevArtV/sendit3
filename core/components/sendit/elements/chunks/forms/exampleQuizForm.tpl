<form data-si-form="quizBig" data-si-preset="quiz">
    <div data-qf-progress>
        <div data-qf-progress-value>0%</div>
    </div>

    <!-- Общие вопросы -->
    <div data-qf-item="1">
        <p>Сфера деятельности Вашей компании или название продукта?</p>
        <input type="hidden" name="questions[1]" value="Сфера деятельности Вашей компании?">
        <label>
            <input type="text" name="answers[1]" placeholder="Продажа вантузов">
            <p data-si-error="answers[1]"></p>
        </label>
    </div>
    <div data-qf-item="2" data-qf-auto="1">
        <p>Есть ли действующий сайт?</p>
        <input type="hidden" name="questions[2]" value="Есть ли действующий сайт?">
        <div>
            <input type="radio" name="answers[2]" data-qf-next="" id="answer-2-1" value="Да">
            <label for="answer-2-1">Да</label>
        </div>
        <div>
            <input type="radio" name="answers[2]" data-qf-next="4" id="answer-2-2" value="Нет">
            <label for="answer-2-2">Нет</label>
        </div>
    </div>
    <div data-qf-item="3">
        <p>Укажите ссылку на действующий сайт и кратко опишите, что Вас в нём не устраивает?</p>
        <input type="hidden" name="questions[3]" value="Что не так с действующим сайтом?">
        <label>
            <textarea name="answers[3]" placeholder="Причины заказа нового сайта"></textarea>
            <p data-si-error="answers[3]"></p>
        </label>
    </div>
    <div data-qf-item="4">
        <p>Укажите 2-3 ссылки на сайты, которые Вам НЕ нравятся и кратко опишите, что именно НЕ нравится?</p>
        <input type="hidden" name="questions[4]" value="Какие сайты не нравятся?">
        <label>
            <textarea name="answers[4]" placeholder="На сайте гугла мне не нравятся кнопки  - портят лаконичность"></textarea>
            <p data-si-error="answers[4]"></p>
        </label>
    </div>
    <div data-qf-item="5">
        <p>Укажите 2-3 ссылки на сайты, которые Вам нравятся и кратко опишите, что именно нравится?</p>
        <input type="hidden" name="questions[5]" value="Какие сайты нравятся?">
        <label>
            <textarea name="answers[5]" placeholder="Мне нравится этот сайт возможностью изменить тему с темной на светлую и обратно"></textarea>
            <p data-si-error="answers[5]"></p>
        </label>
    </div>
    <div data-qf-item="49">
        <p>На каких языках будет публиковаться контент сайта?</p>
        <input type="hidden" name="questions[6]" value="На каких языках будет публиковаться контент сайта?">
        <label>
            <input type="text" name="answers[6]" placeholder="Русский">
            <p data-si-error="answers[6]"></p>
        </label>
    </div>
    <!-- /Общие вопросы -->

    <!-- Вопросы по многостраничнику -->
    <div data-qf-item="38">
        <p>Выберите внутренние страницы сайта?</p>
        <input type="hidden" name="questions[7]" value="Выберите внутренние страницы сайта?">
        <div>
            <input type="checkbox" name="answers[7][]" id="answer-34-1" value="О компании">
            <label for="answer-34-1">О компании</label>
        </div>
        <div>
            <input type="checkbox" name="answers[7][]" id="answer-34-2" value="Контакты">
            <label for="answer-34-2">Контакты</label>
        </div>
        <div>
            <input type="checkbox" name="answers[7][]" id="answer-34-3" value="Блог">
            <label for="answer-34-3">Блог</label>
        </div>
        <div>
            <input type="checkbox" name="answers[7][]" id="answer-34-4" value="Статья">
            <label for="answer-34-4">Статья</label>
        </div>
        <div>
            <input type="checkbox" name="answers[7][]" id="answer-34-5" value="Новости">
            <label for="answer-34-5">Новости</label>
        </div>
        <div>
            <input type="checkbox" name="answers[7][]" id="answer-34-6" value="Новость">
            <label for="answer-34-6">Новость</label>
        </div>
        <div>
            <input type="checkbox" name="answers[7][]" id="answer-34-7" value="Оплата и доставка">
            <label for="answer-34-7">Оплата и доставка</label>
        </div>
        <div>
            <input type="checkbox" name="answers[7][]" id="answer-34-8" value="Кейсы/портфолио/галерея">
            <label for="answer-34-8">Кейсы/портфолио/галерея</label>
        </div>
        <p data-si-error="answers[7][]"></p>
    </div>
    <!-- /Вопросы по многостраничнику -->

    <!-- Финальные блоки -->
    <div data-qf-item="47" data-qf-auto="1">
        <p>
            Отлично! Остался всего один шаг<br>
            и мы подготовим Вам предварительный расчет.
        </p>
        <label>
            <input type="text" name="name" placeholder="Ваше имя">
        </label>
        <label>
            <input type="tel" name="phone" placeholder="+7(">
        </label>
    </div>
    <div data-qf-finish>
        <p>
            Спасибо за потраченное время!<br>
            Ваш подарок: скидка 10% на первый заказ!
        </p>
    </div>
    <!-- /Финальные блоки -->

    <!-- Кнопки управления и пагинация -->
    <div>
        <button data-qf-btn="prev" type="button">Назад</button>
        <span data-qf-pages>
            <span data-qf-page></span>/<span data-qf-total></span>
        </span>
        <button data-qf-btn="next" type="button">Вперед</button>
        <div data-qf-btn="reset">
            <button type="reset">Начать с начала</button>
        </div>
        <button data-qf-btn="send" type="submit">Отправить</button>
    </div>
    <!-- /Кнопки управления и пагинация -->
</form>
