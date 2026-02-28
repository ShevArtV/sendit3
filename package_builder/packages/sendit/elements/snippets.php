<?php

return [
    'siRenderForm' => [
        'description' => 'Renders a SendIt form and stores preset in session',
        'content' => 'file:elements/snippets/snippet.renderform.php',
    ],
    'siPagination' => [
        'description' => 'Handles pagination for SendIt',
        'content' => 'file:elements/snippets/snippet.pagination.php',
    ],
    'siActivateUser' => [
        'description' => 'Activates a user via email link',
        'content' => 'file:elements/snippets/snippet.activateuser.php',
    ],
    'siResetPassword' => [
        'description' => 'Resets password via email link',
        'content' => 'file:elements/snippets/snippet.resetpassword.php',
    ],
];
