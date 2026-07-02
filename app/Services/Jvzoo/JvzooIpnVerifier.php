<?php

namespace App\Services\Jvzoo;

use Illuminate\Http\Request;

class JvzooIpnVerifier
{
    public function verify(Request $request): bool
    {
        $secretKey = (string) config('jvzoo.secret_key');

        if ($secretKey === '' || ! $request->has('cverify')) {
            return false;
        }

        $ipnFields = [];

        foreach ($request->post() as $key => $value) {
            if ($key === 'cverify') {
                continue;
            }

            $ipnFields[] = $key;
        }

        sort($ipnFields);

        $pop = '';

        foreach ($ipnFields as $field) {
            $pop .= $request->post($field).'|';
        }

        $pop .= $secretKey;

        if (mb_detect_encoding($pop) !== 'UTF-8') {
            $pop = mb_convert_encoding($pop, 'UTF-8');
        }

        $calcedVerify = sha1($pop);
        $calcedVerify = strtoupper(substr($calcedVerify, 0, 8));

        return hash_equals($calcedVerify, (string) $request->post('cverify'));
    }
}
