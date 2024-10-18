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

namespace Ripple\RDOMySQL;

use function hash;
use function password_hash;
use function sha1;
use function substr;

use const PASSWORD_ARGON2I;
use const PASSWORD_BCRYPT;

/**
 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/dir_224962caae460612c2a6474fa64a2b05.html
 */
class Encryptor
{
    /**
     * Use native password encryption algorithms.
     *
     * MySQL Note: `MYSQL_NATIVE_PASSWORD` is the default authentication plugin for MySQL 5.6 and earlier.
     * This algorithm uses the SHA-1 hashing algorithm combined with a salt value for encryption.
     *
     * @param string $password User password
     * @param string $salt     salt value
     *
     * @return string encrypted password
     */
    public static function nativePassword(string $password, string $salt): string
    {
        $hash = sha1($password, true);
        return $hash ^ sha1(substr($salt, 0, 20) . sha1($hash, true), true);
    }

    /**
     * Uses SHA-256 encryption algorithm.
     *
     * MySQL Note: `SHA2` is one of the authentication plugins for MySQL 5.6 and higher.
     * This algorithm uses the SHA-256 hash algorithm, providing stronger security.
     *
     * @param string $password User password
     * @param string $salt     salt value
     *
     * @return string encrypted password
     */
    public static function sha2Password(string $password, string $salt): string
    {
        $digestStage1 = Encryptor::hashSHA256($password);
        $digestStage2 = Encryptor::hashSHA256($digestStage1);
        $saltStage1   = Encryptor::hashSHA256($digestStage2 . substr($salt, 0, 20));
        return $digestStage1 ^ $saltStage1;
    }

    /**
     * Calculate the SHA-256 hash value.
     *
     * @param string $data input data
     *
     * @return string hash value
     */
    private static function hashSHA256(string $data): string
    {
        return hash("sha256", $data, true);
    }

    /**
     * Uses the SHA-1 encryption algorithm.
     *
     * MySQL Note: The `SHA1` algorithm can be used to store passwords in MySQL, but is not recommended for new systems.
     * Because it is less secure and vulnerable to collision attacks.
     *
     * @param string $password User password
     * @param string $salt     salt value
     *
     * @return string Encrypted password
     */
    public static function sha1Password(string $password, string $salt): string
    {
        return sha1($password . $salt);
    }

    /**
     * Use bcrypt encryption algorithm.
     *
     * MySQL Note: MySQL 5.7.6 and above support the use of `bcrypt` as an encryption method for storing passwords.
     * bcrypt provides higher security and has a built-in salt value.
     *
     * @param string $password User password
     *
     * @return string Encrypted password
     */
    public static function bcryptPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * Uses the Argon2 encryption algorithm.
     *
     * MySQL Note: MySQL 8.0 and above support the use of `Argon2` as the authentication plug-in.
     * Argon2 is considered one of the most secure password hashing algorithms available.
     *
     * @param string $password User password
     *
     * @return string Encrypted password
     */
    public static function argon2Password(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2I);
    }
}
