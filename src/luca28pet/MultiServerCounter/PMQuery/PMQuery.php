<?php

namespace luca28pet\MultiServerCounter\PMQuery;

use function pack;
use function fclose;
use function fsockopen;
use function fwrite;
use function fread;
use function stream_set_timeout;
use function stream_set_blocking;
use function strlen;
use function str_starts_with;
use function substr;
use function explode;

class PMQuery
{
    /**
     * @param string $host Ip/dns address being queried
     * @param int $port Port on the ip being queried
     * @param int $timeout Seconds before socket times out
     *
     * @return array
     * @throws PmQueryException
     */
    public static function query(string $host, int $port, int $timeout = 4): array
    {
        $socket = @fsockopen('udp://'.$host, $port, $errno, $errstr, $timeout);

        if($errno and $socket !== false) {
            fclose($socket);
            throw new PmQueryException($errstr, $errno);
        }elseif($socket === false) {
            throw new PmQueryException($errstr, $errno);
        }

        stream_set_timeout($socket, $timeout);
        stream_set_blocking($socket, true);

        // hardcoded magic https://github.com/facebookarchive/RakNet/blob/1a169895a900c9fc4841c556e16514182b75faf8/Source/RakPeer.cpp#L135
        $OFFLINE_MESSAGE_DATA_ID = pack('c*', 0x00, 0xFF, 0xFF, 0x00, 0xFE, 0xFE, 0xFE, 0xFE, 0xFD, 0xFD, 0xFD, 0xFD, 0x12, 0x34, 0x56, 0x78);
        $command = pack('cQ', 0x01, time()); // DefaultMessageIDTypes::ID_UNCONNECTED_PING + 64bit current time
        $command .= $OFFLINE_MESSAGE_DATA_ID;
        $command .= pack('Q', 2); // 64bit guid
        $length = strlen($command);

        if($length !== fwrite($socket, $command, $length)) {
            throw new PmQueryException("Failed to write on socket.", E_WARNING);
        }

        $data = fread($socket, 4096);

        fclose($socket);

        if(empty($data)) {
            throw new PmQueryException("Server failed to respond", E_WARNING);
        }
        if(!str_starts_with($data, "\x1C")) {
            throw new PmQueryException("First byte is not ID_UNCONNECTED_PONG.", E_WARNING);
        }
        if(substr($data, 17, 16) !== $OFFLINE_MESSAGE_DATA_ID) {
            throw new PmQueryException("Magic bytes do not match.");
        }

        // TODO: What are the 2 bytes after the magic?
        $data = substr($data, 35);

        // TODO: If server-name contains a ';' it is not escaped, and will break this parsing
        $data = explode(';', $data);

        return [
            'Players' => $data[4] ?? null,
            'MaxPlayers' => $data[5] ?? null,
        ];
    }
}