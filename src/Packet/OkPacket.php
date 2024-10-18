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

namespace Ripple\App\MySQL\Packet;

class OkPacket
{
    /**
     * @param int|null    $title
     * @param int|null    $affectedRows
     * @param int|null    $insertId
     * @param int|null    $serverStatus
     * @param int|null    $warningCount
     * @param string|null $info
     * @param string|null $sessionStateChanges
     */
    public function __construct(
        public readonly int|null    $title,
        public readonly int|null    $affectedRows,
        public readonly int|null    $insertId,
        public readonly int|null    $serverStatus,
        public readonly int|null    $warningCount,
        public readonly string|null $info,
        public readonly string|null $sessionStateChanges
    ) {
    }
}
