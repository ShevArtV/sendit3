<?php
/**
 * Аутентификация, регистрация, обновление профиля, восстановление пароля.
 */

namespace SendIt\Auth;

class Identification
{
    private \modX $modx;
    private array $config;
    private object $hook;
    private array $values;

    /**
     * @param \modX $modx
     * @param object $hook
     * @param array $config
     */
    public function __construct(\modX $modx, object $hook, array $config = [])
    {
        $this->modx = $modx;
        $this->config = $config;
        $this->hook = $hook;
        $this->values = $hook->getValues();
    }

    /**
     * @return string
     */
    public function generateUsername(): string
    {
        return 'user_' . time() . '_' . CodeGenerator::generate($this->modx, 'code', 4);
    }

    /**
     * @return bool
     */
    public function register(): bool
    {
        $email = $this->values['email'] ?? '';
        $passwordField = !empty($this->config['passwordField']) ? $this->config['passwordField'] : 'password';
        $usernameField = !empty($this->config['usernameField']) ? $this->config['usernameField'] : 'username';

        if ($usernameField === 'username' && empty($this->values[$usernameField])) {
            $this->values[$usernameField] = $this->generateUsername();
        } else {
            $this->values['username'] = $this->values[$usernameField];
        }

        if (empty($this->values['email'])) {
            $this->values['email'] = ($this->values[$usernameField] ?? time()) . '@' . $this->modx->getOption('http_host');
        }

        $activation = $this->config['activation'] ?? false;
        $moderate = $this->config['moderate'] ?? false;
        $activationResourceId = $this->config['activationResourceId'] ?? $this->modx->getOption('site_start', '', 1);
        $userGroupsField = $this->config['usergroupsField'] ?? '';
        $defaultUserGroups = explode(',', $this->config['usergroups'] ?? '');

        $this->modx->user = $this->modx->getObject(\MODX\Revolution\modUser::class, 1);

        $userGroups = !empty($userGroupsField) && array_key_exists($userGroupsField, $this->values)
            ? $this->values[$userGroupsField]
            : $defaultUserGroups;

        if (is_string($userGroups)) {
            $userGroups = explode(',', $userGroups);
        }

        if (!empty($userGroups)) {
            foreach ($userGroups as $k => $group) {
                $group = explode(':', $group);
                $this->values['groups'][] = [
                    'usergroup' => $group[0],
                    'role' => $group[1] ?? 1,
                    'rank' => $group[2] ?? $k,
                ];
            }
        }

        if (empty($this->values[$passwordField])) {
            $this->values[$passwordField] = CodeGenerator::generate($this->modx, 'pass', 10);
        }

        $this->values['passwordgenmethod'] = 'none';
        $this->values['specifiedpassword'] = $this->values[$passwordField];
        $this->hook->setValue('password', $this->values[$passwordField]);
        $this->hook->setValue('username', $this->values[$usernameField]);
        $this->values['confirmpassword'] = $this->values[$passwordField];
        $this->values['passwordnotifymethod'] = 's';

        if (!$activation) {
            $this->values['active'] = 1;
        }

        if ($moderate) {
            $this->values['blocked'] = 1;
        }

        $extended = !empty($this->values['extended']) ? str_replace('&quot;', '"', $this->values['extended']) : '';
        $extended = $extended ? json_decode($extended, true) : [];

        if ($this->config['autoLogin']) {
            $extended['autologin'] = [
                'rememberme' => $this->config['rememberme'] ?? 1,
                'authenticateContexts' => $this->config['authenticateContexts'] ?? 'web',
                'afterLoginRedirectId' => $this->config['afterLoginRedirectId'] ?? '',
            ];
        }

        $this->values['extended'] = $extended;

        $response = $this->modx->runProcessor('Security/User/Create', $this->values);

        if ($errors = $response->getFieldErrors()) {
            foreach ($errors as $error) {
                $key = $error->getField();
                if ($error->getField() === 'username') {
                    $key = $usernameField;
                }
                if (in_array($error->getField(), ['password', 'specifiedpassword'])) {
                    $key = $passwordField;
                }
                $this->hook->addError($key, $error->getMessage());
            }
            return false;
        }

        $this->modx->user = $this->modx->getObject(\MODX\Revolution\modUser::class, $response->response['object']['id']);

        if ($activation && !empty($email) && !empty($activationResourceId)) {
            $confirmUrl = $this->getConfirmUrl((int)$activationResourceId);
            $this->hook->setValue('confirmUrl', $confirmUrl);
        }

        if ($this->config['autoLogin'] == true && !$activation && !$moderate) {
            return $this->login();
        }

        return true;
    }

    /**
     * @param string $username
     * @param \modX $modx
     * @param array $properties
     * @return bool
     */
    public static function loginWithoutPass(string $username, \modX $modx, array $properties = []): bool
    {
        $lifetime = (int)$modx->getOption('session_cookie_lifetime', null, 0);
        $contexts = !empty($properties['authenticateContexts'])
            ? explode(',', $properties['authenticateContexts'])
            : ['web'];

        $q = $modx->newQuery(\MODX\Revolution\modUser::class);
        $q->leftJoin(\MODX\Revolution\modUserProfile::class, 'Profile');
        $q->select($modx->getSelectColumns(\MODX\Revolution\modUser::class, 'modUser', '', ['id', 'username', 'active']));
        $q->select($modx->getSelectColumns(\MODX\Revolution\modUserProfile::class, 'Profile', '', ['blocked']));
        $q->where(['modUser.username' => $username, 'modUser.active' => 1, 'Profile.blocked' => 0]);
        $user = $modx->getObject(\MODX\Revolution\modUser::class, $q);

        if (!$user) {
            $modx->log(\modX::LOG_LEVEL_ERROR, "[SendIt|Identification::loginWithoutPass] Пользователь $username не существует, не активирован или заблокирован.");
            return false;
        }

        $session_id = session_id();
        foreach ($contexts as $ctx) {
            $user->addSessionContext($ctx);
            $_SESSION['modx.' . $ctx . '.session.cookie.lifetime'] = ($properties['rememberme'] ?? 0) ? $lifetime : 0;
        }
        $modx->user = $user;

        $modx->invokeEvent('OnWebLogin', [
            'user' => $user,
            'attributes' => $properties['rememberme'] ?? 0,
            'lifetime' => $modx->getOption('session_gc_maxlifetime'),
            'loginContext' => $modx->context->key,
            'addContexts' => $properties['authenticateContexts'] ?? '',
            'session_id' => $session_id,
        ]);

        $user = $modx->getObject(\MODX\Revolution\modUser::class, $q);
        $profile = $user->getOne('Profile');
        $extended = $profile->get('extended');
        unset($extended['autologin']);
        $profile->set('extended', $extended);
        $profile->save();

        return true;
    }

    /**
     * @return bool
     */
    public function login(): bool
    {
        $contexts = $this->config['authenticateContexts'] ?? '';
        $passwordField = !empty($this->config['passwordField']) ? $this->config['passwordField'] : 'password';
        $usernameField = !empty($this->config['usernameField']) ? $this->config['usernameField'] : 'username';

        if (!($this->values[$usernameField] ?? '') || !($this->values[$passwordField] ?? '')) {
            $this->hook->addError($this->config['errorFieldName'], $this->modx->lexicon('si_msg_login_err'));
            return false;
        }

        if (!$username = $this->getUsername($usernameField, $this->values[$usernameField])) {
            $this->hook->addError($this->config['errorFieldName'], $this->modx->lexicon('si_msg_username_err'));
            return false;
        }

        $c = [
            'login_context' => $this->modx->context->key,
            'add_contexts' => $contexts,
            'username' => $username,
            'password' => $this->values[$passwordField],
            'rememberme' => $this->values['rememberme'] ?? 0,
        ];

        $response = $this->modx->runProcessor('Security/Login', $c);
        if ($response->isError()) {
            $this->hook->addError($this->config['errorFieldName'] ?? 'errorLogin', $response->getMessage());
            return false;
        }

        return true;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return string|false
     */
    public function getUsername(string $key, mixed $value): string|false
    {
        $userFields = $this->getTableColumns(\MODX\Revolution\modUser::class);
        if (in_array($key, $userFields)) {
            $key = 'modUser.' . $key;
        } else {
            $profileFields = $this->getTableColumns(\MODX\Revolution\modUserProfile::class);
            if (in_array($key, $profileFields)) {
                $key = 'Profile.' . $key;
            }
        }

        $q = $this->modx->newQuery(\MODX\Revolution\modUser::class);
        $q->leftJoin(\MODX\Revolution\modUserProfile::class, 'Profile');
        $q->where([$key => $value]);
        $q->select('username');
        $q->limit(1);
        $q->prepare();

        if ($q->stmt->execute()) {
            return $q->stmt->fetch(\PDO::FETCH_COLUMN);
        }

        return '';
    }

    /**
     * @param string $className
     * @return string[]
     */
    public function getTableColumns(string $className): array
    {
        $tableName = $this->modx->getTableName($className);
        $tableName = str_replace('`', '\'', $tableName);
        $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = $tableName";
        $stmt = $this->modx->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @return bool
     */
    public function update(): bool
    {
        if (isset($this->values['uid'])) {
            $user = $this->modx->getObject(\MODX\Revolution\modUser::class, (int)$this->values['uid']);
        } else {
            $user = $this->modx->user;
        }

        if ($this->modx->user->isAuthenticated($this->modx->context->get('key'))) {
            $profile = $user->getOne('Profile');
            $profileData = $profile->toArray();
            $profileExtended = $profileData['extended'] ?? [];
            $extended = !empty($this->values['extended']) ? str_replace('&quot;', '"', $this->values['extended']) : '';
            $extended = $extended ? json_decode($extended, true) : [];
            $this->values['extended'] = array_merge($profileExtended, $extended);
            $this->values['dob'] = !empty($this->values['dob']) ? strtotime($this->values['dob']) : $profile->get('dob');
            $userData = $user->toArray();
            unset($userData['password'], $userData['cachepwd']);

            $user->fromArray(array_merge($userData, $this->values));
            $profile->fromArray(array_merge($profileData, $this->values));
            $user->save();
            $profile->save();

            $this->modx->invokeEvent('siOnUserUpdate', [
                'user' => $user,
                'profile' => $profile,
                'data' => $this->values,
            ]);
        }

        return true;
    }

    /**
     * @return bool
     */
    public function logout(): bool
    {
        $contexts = $this->config['authenticateContexts'] ?? '';
        $response = $this->modx->runProcessor('Security/Logout', [
            'login_context' => $this->modx->context->key,
            'add_contexts' => $contexts,
        ]);

        if ($response->isError()) {
            $this->hook->addError($this->config['errorFieldName'], $response->getMessage());
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function forgot(): bool
    {
        $usernameField = $this->config['usernameField'] ?? 'username';
        $username = $this->values[$usernameField];

        if ($usernameField !== 'username') {
            $username = $this->getUsername($usernameField, $this->values[$usernameField]);
        }

        $activationResourceId = $this->config['activationResourceId']
            ?? $this->modx->getOption('site_start', '', 1);
        $user = $this->modx->getObject(\MODX\Revolution\modUser::class, ['username' => $username]);

        if ($user) {
            $profile = $user->getOne('Profile');
            if (!$profile->get('email')) {
                $this->hook->addError($this->config['errorFieldName'], $this->modx->lexicon('si_msg_no_email_err'));
                return false;
            }

            $extended = $profile->get('extended');
            $extended['activate_pass_before'] = time() + ($this->config['activationUrlTime'] ?: 60 * 60 * 3);
            $extended['temp_password'] = CodeGenerator::generate($this->modx);

            if ($this->config['autoLogin']) {
                $extended['autologin'] = [
                    'rememberme' => $this->config['rememberme'] ?? 1,
                    'authenticateContexts' => $this->config['authenticateContexts'] ?? 'web',
                    'afterLoginRedirectId' => $this->config['afterLoginRedirectId'] ?? '',
                ];
            }

            $profile->set('extended', $extended);
            $profile->save();

            $confirmUrl = $this->modx->makeUrl(
                $activationResourceId,
                '',
                'rp=' . CodeGenerator::base64urlEncode($user->get('username')),
                'full'
            );

            $this->hook->setValue('password', $extended['temp_password']);
            $this->hook->setValue('email', $profile->get('email'));
            $this->hook->setValue('confirmUrl', $confirmUrl);
        }

        return true;
    }

    /**
     * @param int $activationResourceId
     * @return string
     */
    public function getConfirmUrl(int $activationResourceId): string
    {
        $profile = $this->modx->user->getOne('Profile');
        $extended = $profile->get('extended');
        $extended['activate_before'] = time() + ($this->config['activationUrlTime'] ?? 60 * 60 * 3);
        $profile->set('extended', $extended);
        $profile->save();

        $args = 'lu=' . CodeGenerator::base64urlEncode($this->modx->user->get('username'));

        return $this->modx->makeUrl($activationResourceId, '', $args, 'full');
    }

    /**
     * @param string $username
     * @param \modX $modx
     * @param string $toPls
     * @return array
     */
    public static function activateUser(string $username, \modX $modx, string $toPls = ''): array
    {
        $userData = [];
        $user = $modx->getObject(\MODX\Revolution\modUser::class, ['username' => $username]);

        if (!$user) {
            return $userData;
        }

        $profile = $user->getOne('Profile');
        $extended = $profile->get('extended');

        if (!$user->get('active') && ($extended['activate_before'] ?? 0) - time() <= 0) {
            $user->remove();
            return $userData;
        }

        $userData = array_merge($profile->toArray(), $user->toArray());

        if (($extended['activate_before'] ?? 0) - time() > 0) {
            $user->set('active', 1);
            $user->save();
            unset($extended['activate_before']);
            $profile->set('extended', $extended);
            $profile->save();
        }

        $modx->invokeEvent('OnUserActivate', [
            'user' => $user,
            'profile' => $profile,
            'data' => $userData,
        ]);

        if ($toPls && $userData) {
            $modx->setPlaceholder($toPls, $userData);
        }

        return $userData;
    }

    /**
     * @param string $username
     * @param \modX $modx
     * @param string $toPls
     * @return array
     */
    public static function resetPassword(string $username, \modX $modx, string $toPls = ''): array
    {
        $user = $modx->getObject(\MODX\Revolution\modUser::class, ['username' => $username]);
        if (!$user) {
            return [];
        }

        $profile = $user->getOne('Profile');
        $extended = $profile->get('extended');
        $password = $extended['temp_password'] ?? '';
        $activateBefore = $extended['activate_pass_before'] ?? 0;

        unset($extended['activate_pass_before'], $extended['temp_password']);
        $profile->set('extended', $extended);
        $profile->save();

        if ($activateBefore - time() <= 0) {
            return [];
        }

        if ($password) {
            $user->set('password', $password);
            $user->save();
        }

        $userData = array_merge($profile->toArray(), $user->toArray());

        if ($toPls && $userData) {
            $modx->setPlaceholder($toPls, $userData);
        }

        return $userData;
    }

    // ========== Deprecated proxies ==========

    /**
     * @deprecated Используйте CodeGenerator::generate($modx, $type, $length)
     */
    public static function generateCode(\modX $modx, string $type = 'pass', int $length = 0): string
    {
        return CodeGenerator::generate($modx, $type, $length);
    }

    /**
     * @deprecated Используйте CodeGenerator::base64urlEncode($str)
     */
    public function base64url_encode(string $str): string
    {
        return CodeGenerator::base64urlEncode($str);
    }

    /**
     * @deprecated Используйте CodeGenerator::base64urlDecode($str)
     */
    public static function base64url_decode(string $str): string
    {
        return CodeGenerator::base64urlDecode($str);
    }
}
