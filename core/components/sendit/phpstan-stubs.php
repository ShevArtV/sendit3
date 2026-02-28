<?php
/**
 * PHPStan stubs для устаревших глобальных алиасов MODX 3.
 * MODX 3 создаёт эти алиасы в runtime через class_alias(),
 * но PHPStan не выполняет код, поэтому нужны явные объявления.
 */

class_alias(\MODX\Revolution\modX::class, 'modX');
class_alias(\xPDO\xPDO::class, 'xPDO');
class_alias(\xPDO\Om\xPDOSimpleObject::class, 'xPDOSimpleObject');
class_alias(\xPDO\Om\xPDOObject::class, 'xPDOObject');
class_alias(\xPDO\Om\xPDOQuery::class, 'xPDOQuery');
class_alias(\MODX\Revolution\modCacheManager::class, 'modCacheManager');
class_alias(\MODX\Revolution\modSystemEvent::class, 'modSystemEvent');
class_alias(\MODX\Revolution\modUser::class, 'modUser');
class_alias(\MODX\Revolution\modUserProfile::class, 'modUserProfile');
class_alias(\MODX\Revolution\modResource::class, 'modResource');
class_alias(\MODX\Revolution\modChunk::class, 'modChunk');
class_alias(\MODX\Revolution\modSnippet::class, 'modSnippet');

define('MODX_CORE_PATH', '/home/shevartv/projects/apps/sendit3/core/');
define('MODX_ASSETS_PATH', '/home/shevartv/projects/apps/sendit3/assets/');
