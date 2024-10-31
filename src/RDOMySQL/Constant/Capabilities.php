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

/**
 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/group__group__cs__capabilities__flags.html
 */
enum Capabilities: int
{
    case CLIENT_LONG_PASSWORD                  = 1;         // Uses an improved version of old password authentication.
    case CLIENT_FOUND_ROWS                     = 2;         // Send the rows found in the EOF_Packet instead of the affected rows.
    case CLIENT_LONG_FLAG                      = 4;         // Get all column flags.
    case CLIENT_CONNECT_WITH_DB                = 8;         // The database (schema) name can be specified on the connection in the handshake response packet.
    case CLIENT_NO_SCHEMA                      = 16;        // Deprecated: database.table.column is not allowed.
    case CLIENT_COMPRESS                       = 32;        // Supports compression protocols.
    case CLIENT_ODBC                           = 64;        // Special handling of ODBC behavior.
    case CLIENT_LOCAL_FILES                    = 128;       // LOAD DATA LOCAL can be used.
    case CLIENT_IGNORE_SPACE                   = 256;       // Ignore spaces before '('.
    case CLIENT_PROTOCOL_41                    = 512;       // New 4.1 protocol.
    case CLIENT_INTERACTIVE                    = 1024;      // This is an interactive client.
    case CLIENT_SSL                            = 2048;      // Use SSL encryption for sessions.
    case CLIENT_IGNORE_SIGPIPE                 = 4096;      // Client flag only.
    case CLIENT_TRANSACTIONS                   = 8192;      // The client understands the transaction.
    case CLIENT_RESERVED                       = 16384;     // Deprecated: Old flag for the 4.1 protocol.
    case CLIENT_RESERVED2                      = 32768;     // Deprecated: Old flag for 4.1 authentication \CLIENT_SECURE_CONNECTION.
    case CLIENT_MULTI_STATEMENTS               = (1 << 16); // Enable/disable multi-stmt support.
    case CLIENT_MULTI_RESULTS                  = (1 << 17); // Enable/disable multiple results.
    case CLIENT_PS_MULTI_RESULTS               = (1 << 18); // Multiple results and OUT parameters in PS protocol.
    case CLIENT_PLUGIN_AUTH                    = (1 << 19); // The client supports plug-in authentication.
    case CLIENT_CONNECT_ATTRS                  = (1 << 20); // The client supports connection properties.
    case CLIENT_PLUGIN_AUTH_LENENC_CLIENT_DATA = (1 << 21); // Authentication response messages larger than 255 bytes are allowed.
    case CLIENT_CAN_HANDLE_EXPIRED_PASSWORDS   = (1 << 22); // Do not close connections for user accounts with expired passwords.
    case CLIENT_SESSION_TRACK                  = (1 << 23); // Able to handle server status change information.
    case CLIENT_DEPRECATE_EOF                  = (1 << 24); // Clients no longer require EOF_Packet and use OK_Packet instead.
    case CLIENT_OPTIONAL_RESULTSET_METADATA    = (1 << 25); // The client can handle optional metadata information in the result set.
    case CLIENT_ZSTD_COMPRESSION_ALGORITHM     = (1 << 26); // Extended compression protocol to support zstd compression method.
    case CLIENT_QUERY_ATTRIBUTES               = (1 << 27); // Supports optional extension of query parameters to COM_QUERY and COM_STMT_EXECUTE packets.
    case MULTI_FACTOR_AUTHENTICATION           = (1 << 28); // Supports multi-factor authentication.
    case CLIENT_CAPABILITY_EXTENSION           = (1 << 29); // This flag will be reserved to extend 32-bit functional structures to 64-bit.
    case CLIENT_SSL_VERIFY_SERVER_CERT         = (1 << 30); // Verify the server certificate.
    case CLIENT_REMEMBER_OPTIONS               = (1 << 31); // Do not reset options after connection failure.
}
