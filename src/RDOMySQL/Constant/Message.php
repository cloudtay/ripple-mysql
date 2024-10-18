<?php declare(strict_types=1);
/**
 * Copyright © 2024 cclilshy
 * Email: jingnigg@gmail.com
 *
 * This software is licensed under the MIT License.
 * For full license details, please visit: https://opensource.org/licenses/MIT
 *
 * By using this software, you agree to the terms of the license.
 * Contributions, suggestions, and feedback are always welcome!
 */

namespace Ripple\RDOMySQL\Constant;

enum Message: string
{
    case SERVER_STATUS_IN_TRANS             = 'In transaction';
    case SERVER_STATUS_AUTOCOMMIT           = 'Auto-commit';
    case SERVER_MORE_RESULTS_EXISTS         = 'More results exists';
    case SERVER_QUERY_NO_GOOD_INDEX_USED    = 'No good index used';
    case SERVER_QUERY_NO_INDEX_USED         = 'No index used';
    case SERVER_STATUS_CURSOR_EXISTS        = 'Cursor exists';
    case SERVER_STATUS_LAST_ROW_SENT        = 'Last row sent';
    case SERVER_STATUS_DB_DROPPED           = 'Database dropped';
    case SERVER_STATUS_NO_BACKSLASH_ESCAPES = 'No backslash escapes';
    case SERVER_STATUS_METADATA_CHANGED     = 'Metadata changed';
    case SERVER_QUERY_WAS_SLOW              = 'Query was slow';
    case SERVER_PS_OUT_PARAMS               = 'PS out params';
    case SERVER_STATUS_IN_TRANS_READONLY    = 'In transaction read-only';
    case SERVER_SESSION_STATE_CHANGED       = 'Session state changed';
}
