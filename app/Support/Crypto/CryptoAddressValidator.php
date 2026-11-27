<?php

namespace App\Support\Crypto;

final class CryptoAddressValidator
{
    public function isValid(string $address, CryptoRail $rail, string $mode): bool
    {
        if (!in_array($mode, ['mainnet', 'testnet', 'regtest'], true)
            || $address !== trim($address) || strlen($address) > 90 || $address === '') {
            return false;
        }

        if ($rail->addressFamily === 'tron') {
            // TRON test networks share address encoding. This checks syntax, NOT chain provenance.
            return $this->base58Version($address) === 0x41;
        }

        $hrp = $rail->asset === 'BTC'
            ? ['mainnet' => 'bc', 'testnet' => 'tb', 'regtest' => 'bcrt'][$mode]
            : ['mainnet' => 'ltc', 'testnet' => 'tltc', 'regtest' => 'rltc'][$mode];
        if (str_starts_with(strtolower($address), $hrp . '1')) {
            return $this->segwit($address, $hrp);
        }

        $versions = $mode === 'mainnet'
            ? ($rail->asset === 'BTC' ? [0, 5] : [48, 50])
            : ($rail->asset === 'BTC' ? [111, 196] : [111, 58]);

        // Reject Litecoin's legacy shared BTC P2SH prefix rather than guess the chain.
        return in_array($this->base58Version($address), $versions, true);
    }

    private function base58Version(string $address): ?int
    {
        if (strlen($address) < 26 || strlen($address) > 35) {
            return null;
        }
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $bytes = [0];
        foreach (str_split($address) as $char) {
            $carry = strpos($alphabet, $char);
            if ($carry === false) {
                return null;
            }
            for ($i = count($bytes) - 1; $i >= 0; --$i) {
                $carry += $bytes[$i] * 58;
                $bytes[$i] = $carry & 255;
                $carry >>= 8;
            }
            while ($carry > 0) {
                array_unshift($bytes, $carry & 255);
                $carry >>= 8;
            }
        }
        $decoded = str_repeat("\0", strspn($address, '1')) . pack('C*', ...$bytes);
        if (strlen($decoded) !== 25) {
            return null;
        }
        $payload = substr($decoded, 0, 21);
        $checksum = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);

        return hash_equals($checksum, substr($decoded, 21)) ? ord($decoded[0]) : null;
    }

    private function segwit(string $address, string $hrp): bool
    {
        if ($address !== strtolower($address) && $address !== strtoupper($address)) {
            return false;
        }
        $address = strtolower($address);
        $alphabet = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';
        $data = [];
        foreach (str_split(substr($address, strlen($hrp) + 1)) as $char) {
            $value = strpos($alphabet, $char);
            if ($value === false) {
                return false;
            }
            $data[] = $value;
        }
        if (count($data) < 7 || $data[0] > 16) {
            return false;
        }
        $values = array_merge(
            array_map(fn ($c) => ord($c) >> 5, str_split($hrp)), [0],
            array_map(fn ($c) => ord($c) & 31, str_split($hrp)), $data
        );
        $check = 1;
        foreach ($values as $value) {
            $top = $check >> 25;
            $check = (($check & 0x1ffffff) << 5) ^ $value;
            foreach ([0x3b6a57b2, 0x26508e6d, 0x1ea119fa, 0x3d4233dd, 0x2a1462b3] as $i => $generator) {
                if (($top >> $i) & 1) {
                    $check ^= $generator;
                }
            }
        }
        if ($check !== ($data[0] === 0 ? 1 : 0x2bc830a3)) {
            return false;
        }
        $bits = $accumulator = $length = 0;
        foreach (array_slice($data, 1, -6) as $value) {
            $accumulator = (($accumulator << 5) | $value) & 0xfff;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                ++$length;
            }
        }

        return $bits < 5 && (($accumulator << (8 - $bits)) & 255) === 0
            && $length >= 2 && $length <= 40
            && ($data[0] !== 0 || in_array($length, [20, 32], true));
    }
}
