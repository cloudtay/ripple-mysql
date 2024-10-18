<?php declare(strict_types=1);

namespace Ripple\App\MySQL\Constant;

enum Status: int
{
    case SERVER_STATUS_IN_TRANS             = 0x0001;
    case SERVER_STATUS_AUTOCOMMIT           = 0x0002;
    case SERVER_MORE_RESULTS_EXISTS         = 0x0008;
    case SERVER_QUERY_NO_GOOD_INDEX_USED    = 0x0010;
    case SERVER_QUERY_NO_INDEX_USED         = 0x0020;
    case SERVER_STATUS_CURSOR_EXISTS        = 0x0040;
    case SERVER_STATUS_LAST_ROW_SENT        = 0x0080;
    case SERVER_STATUS_DB_DROPPED           = 0x0100;
    case SERVER_STATUS_NO_BACKSLASH_ESCAPES = 0x0200;
    case SERVER_STATUS_METADATA_CHANGED     = 0x0400;
    case SERVER_QUERY_WAS_SLOW              = 0x0800;
    case SERVER_PS_OUT_PARAMS               = 0x1000;
    case SERVER_STATUS_IN_TRANS_READONLY    = 0x2000;
    case SERVER_SESSION_STATE_CHANGED       = 0x4000;
}
