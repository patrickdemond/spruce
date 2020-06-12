<?php
/**
 * error_codes.inc.php
 * 
 * This file is where all error codes are defined.
 * All error code are named after the class and function they occur in.
 */

/**
 * Error number category defines.
 */
define( 'ARGUMENT_PINE_BASE_ERRNO',   130000 );
define( 'DATABASE_PINE_BASE_ERRNO',   230000 );
define( 'LDAP_PINE_BASE_ERRNO',       330000 );
define( 'NOTICE_PINE_BASE_ERRNO',     430000 );
define( 'PERMISSION_PINE_BASE_ERRNO', 530000 );
define( 'RUNTIME_PINE_BASE_ERRNO',    630000 );
define( 'SYSTEM_PINE_BASE_ERRNO',     730000 );

/**
 * "argument" error codes
 */
define( 'ARGUMENT__PINE_BUSINESS_EXPRESSION_MANAGER__EVALUATE__ERRNO',
        ARGUMENT_PINE_BASE_ERRNO + 1 );
define( 'ARGUMENT__PINE_BUSINESS_EXPRESSION_MANAGER__COMPILE__ERRNO',
        ARGUMENT_PINE_BASE_ERRNO + 2 );
define( 'ARGUMENT__PINE_BUSINESS_EXPRESSION_MANAGER__PROCESS_QUESTION__ERRNO',
        ARGUMENT_PINE_BASE_ERRNO + 3 );

/**
 * "database" error codes
 * 
 * Since database errors already have codes this list is likely to stay empty.
 */

/**
 * "ldap" error codes
 * 
 * Since ldap errors already have codes this list is likely to stay empty.
 */

/**
 * "notice" error codes
 */
define( 'NOTICE__PINE_DATABASE_BASE_DESCRIPTION__SAVE__ERRNO',
        NOTICE_PINE_BASE_ERRNO + 1 );
define( 'NOTICE__PINE_DATABASE_BASE_QNAIRE_PART__SAVE__ERRNO',
        NOTICE_PINE_BASE_ERRNO + 2 );
define( 'NOTICE__PINE_DATABASE_MODULE__GET_PREVIOUS_FOR_RESPONSE__ERRNO',
        NOTICE_PINE_BASE_ERRNO + 3 );
define( 'NOTICE__PINE_DATABASE_MODULE__GET_NEXT_FOR_RESPONSE__ERRNO',
        NOTICE_PINE_BASE_ERRNO + 4 );
define( 'NOTICE__PINE_DATABASE_PAGE__GET_PREVIOUS_FOR_RESPONSE__ERRNO',
        NOTICE_PINE_BASE_ERRNO + 5 );
define( 'NOTICE__PINE_DATABASE_PAGE__GET_NEXT_FOR_RESPONSE__ERRNO',
        NOTICE_PINE_BASE_ERRNO + 6 );
define( 'NOTICE__PINE_DATABASE_QNAIRE__SAVE__ERRNO',
        NOTICE_PINE_BASE_ERRNO + 7 );
define( 'NOTICE__PINE_SERVICE_QNAIRE_PATCH__EXECUTE__ERRNO',
        NOTICE_PINE_BASE_ERRNO + 8 );

/**
 * "permission" error codes
 */

/**
 * "runtime" error codes
 */
define( 'RUNTIME__PINE_BUSINESS_EXPRESSION_MANAGER__EVALUATE__ERRNO',
        RUNTIME_PINE_BASE_ERRNO + 1 );
define( 'RUNTIME__PINE_BUSINESS_EXPRESSION_MANAGER__COMPILE__ERRNO',
        RUNTIME_PINE_BASE_ERRNO + 2 );
define( 'RUNTIME__PINE_BUSINESS_EXPRESSION_MANAGER__PROCESS_STRING__ERRNO',
        RUNTIME_PINE_BASE_ERRNO + 3 );
define( 'RUNTIME__PINE_BUSINESS_EXPRESSION_MANAGER__PROCESS_NUMBER__ERRNO',
        RUNTIME_PINE_BASE_ERRNO + 4 );
define( 'RUNTIME__PINE_BUSINESS_EXPRESSION_MANAGER__PROCESS_CONSTANT__ERRNO',
        RUNTIME_PINE_BASE_ERRNO + 5 );
define( 'RUNTIME__PINE_BUSINESS_EXPRESSION_MANAGER__PROCESS_OPERATOR__ERRNO',
        RUNTIME_PINE_BASE_ERRNO + 6 );
define( 'RUNTIME__PINE_BUSINESS_EXPRESSION_MANAGER__PROCESS_ATTRIBUTE__ERRNO',
        RUNTIME_PINE_BASE_ERRNO + 7 );
define( 'RUNTIME__PINE_BUSINESS_EXPRESSION_MANAGER__PROCESS_QUESTION__ERRNO',
        RUNTIME_PINE_BASE_ERRNO + 8 );
define( 'RUNTIME__PINE_BUSINESS_EXPRESSION_MANAGER__PROCESS_CHARACTER__ERRNO',
        RUNTIME_PINE_BASE_ERRNO + 9 );
define( 'RUNTIME__PINE_DATABASE_BASE_DESCRIPTION__GET_QNAIRE__ERRNO',
        RUNTIME_PINE_BASE_ERRNO + 10 );
define( 'RUNTIME__PINE_DATABASE_QNAIRE__GENERATE__ERRNO',
        RUNTIME_PINE_BASE_ERRNO + 11 );
define( 'RUNTIME__PINE_DATABASE_RESPONDENT__GENERATE_TOKEN__ERRNO',
        RUNTIME_PINE_BASE_ERRNO + 12 );
define( 'RUNTIME__PINE_DATABASE_RESPONSE__SAVE__ERRNO',
        RUNTIME_PINE_BASE_ERRNO + 13 );

/**
 * "system" error codes
 * 
 * Since system errors already have codes this list is likely to stay empty.
 * Note the following PHP error codes:
 *      1: error,
 *      2: warning,
 *      4: parse,
 *      8: notice,
 *     16: core error,
 *     32: core warning,
 *     64: compile error,
 *    128: compile warning,
 *    256: user error,
 *    512: user warning,
 *   1024: user notice
 */

