<?php

return [
    'RenderForm' => [
        'description' => 'Renders a SendIt form and stores preset in session',
        'content' => 'file:elements/snippets/snippet.renderform.php',
    ],
    'Pagination' => [
        'description' => 'Handles pagination for SendIt',
        'content' => 'file:elements/snippets/snippet.pagination.php',
    ],
    'ActivateUser' => [
        'description' => 'Activates a user via email link',
        'content' => 'file:elements/snippets/snippet.activateuser.php',
    ],
    'PasswordReset' => [
        'description' => 'Resets password via email link',
        'content' => 'file:elements/snippets/snippet.resetpassword.php',
    ],
    'Identification' => [
        'description' => 'Hook for user identification (login, register, update, logout, forgot)',
        'content' => 'file:elements/snippets/hooks/hook.identification.php',
    ],
    'requiredIf' => [
        'description' => 'Validator: makes a field required based on another field value',
        'content' => 'file:elements/snippets/validators/validator.requiredif.php',
    ],
    'checkPassLength' => [
        'description' => 'Validator: checks password length only when password is provided',
        'content' => 'file:elements/snippets/validators/validator.checkpasslength.php',
    ],
    'passwordConfirm' => [
        'description' => 'Validator: confirms password match only when password is provided',
        'content' => 'file:elements/snippets/validators/validator.passwordconfirm.php',
    ],
    'userNotExists' => [
        'description' => 'Validator: returns error if user is NOT found',
        'content' => 'file:elements/snippets/validators/validator.usernotexists.php',
    ],
    'userExists' => [
        'description' => 'Validator: returns error if user IS found',
        'content' => 'file:elements/snippets/validators/validator.userexists.php',
    ],
];
