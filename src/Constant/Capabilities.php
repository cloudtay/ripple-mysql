<?php declare(strict_types=1);

namespace Ripple\App\MySQL\Constant;

/**
 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/group__group__cs__capabilities__flags.html
 */
class Capabilities
{
    public const  CLIENT_LONG_PASSWORD                  = 1;         // 使用旧密码验证的改进版本。
    public const  CLIENT_FOUND_ROWS                     = 2;         // 发送 EOF_Packet 中找到的行而不是受影响的行。
    public const  CLIENT_LONG_FLAG                      = 4;         // 获取所有列标志。
    public const  CLIENT_CONNECT_WITH_DB                = 8;         // 数据库（模式）名称可以在握手响应数据包中的连接上指定。
    public const  CLIENT_NO_SCHEMA                      = 16;        // 已弃用：不允许使用database.table.column。
    public const  CLIENT_COMPRESS                       = 32;        // 支持压缩协议。
    public const  CLIENT_ODBC                           = 64;        // ODBC 行为的特殊处理。
    public const  CLIENT_LOCAL_FILES                    = 128;       // 可以使用 LOAD DATA LOCAL。
    public const  CLIENT_IGNORE_SPACE                   = 256;       // 忽略 '(' 之前的空格。
    public const  CLIENT_PROTOCOL_41                    = 512;       // 新的 4.1 协议。
    public const  CLIENT_INTERACTIVE                    = 1024;      // 这是一个交互式客户端。
    public const  CLIENT_SSL                            = 2048;      // 对会话使用 SSL 加密。
    public const  CLIENT_IGNORE_SIGPIPE                 = 4096;      // 仅客户端标志。
    public const  CLIENT_TRANSACTIONS                   = 8192;      // 客户端了解交易。
    public const  CLIENT_RESERVED                       = 16384;     // 已弃用：4.1 协议的旧标志。
    public const  CLIENT_RESERVED2                      = 32768;     // 已弃用：4.1 身份验证的旧标志 \ CLIENT_SECURE_CONNECTION。
    public const  CLIENT_MULTI_STATEMENTS               = (1 << 16); // 启用/禁用多 stmt 支持。
    public const  CLIENT_MULTI_RESULTS                  = (1 << 17); // 启用/禁用多结果。
    public const  CLIENT_PS_MULTI_RESULTS               = (1 << 18); // PS 协议中的多结果和 OUT 参数。
    public const  CLIENT_PLUGIN_AUTH                    = (1 << 19); // 客户端支持插件认证。
    public const  CLIENT_CONNECT_ATTRS                  = (1 << 20); // 客户端支持连接属性。
    public const  CLIENT_PLUGIN_AUTH_LENENC_CLIENT_DATA = (1 << 21); // 允许认证响应报文大于255字节。
    public const  CLIENT_CAN_HANDLE_EXPIRED_PASSWORDS   = (1 << 22); // 不要关闭密码过期的用户帐户的连接。
    public const  CLIENT_SESSION_TRACK                  = (1 << 23); // 能够处理服务器状态变化信息。
    public const  CLIENT_DEPRECATE_EOF                  = (1 << 24); // 客户端不再需要 EOF_Packet，而是使用 OK_Packet。
    public const  CLIENT_OPTIONAL_RESULTSET_METADATA    = (1 << 25); // 客户端可以处理结果集中的可选元数据信息。
    public const  CLIENT_ZSTD_COMPRESSION_ALGORITHM     = (1 << 26); // 扩展压缩协议以支持 zstd 压缩方法。
    public const  CLIENT_QUERY_ATTRIBUTES               = (1 << 27); // 支持将查询参数可选扩展到 COM_QUERY 和 COM_STMT_EXECUTE 数据包中。
    public const  MULTI_FACTOR_AUTHENTICATION           = (1 << 28); // 支持多因素身份验证。
    public const  CLIENT_CAPABILITY_EXTENSION           = (1 << 29); // 该标志将被保留以将 32 位功能结构扩展到 64 位。
    public const  CLIENT_SSL_VERIFY_SERVER_CERT         = (1 << 30); // 验证服务器证书。
    public const  CLIENT_REMEMBER_OPTIONS               = (1 << 31); // 连接失败后不要重置选项。
    public const  CLIENT_SECURE_CONNECTION              = Capabilities::CLIENT_RESERVED2;
    public const  SERVER_SESSION_STATE_CHANGED          = 1 << 30;  // 服务器状态已更改。
}
