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

class EofPacket
{
    /**
     * @param int $title
     * @param int $warningCount
     * @param int $serverStatus
     */
    public function __construct(
        public readonly int $title,
        public readonly int $warningCount,
        public readonly int $serverStatus
    ) {
    }
}
