<?php

namespace App\Classes;

use Illuminate\Support\Facades\Log;
use App\Exceptions\NapbotsAuthException;
use App\Exceptions\NapbotsNotResponding;
use App\Exceptions\NapbotsUnauthenticated;
use App\Exceptions\NapbotsInvalidInfosException;
use App\Exceptions\NapbotsInvalidCryptoWeatherException;

/**
 * Class Napbots.
 */
class Napbots
{
    /**
     * @var
     */
    public $email;

    /**
     * @var
     */
    public $password;

    /**
     * @var
     */
    public $userId;

    /**
     * @var
     */
    private $authToken;

    /**
     * Authenticate to Napbots.
     * @param $email
     * @param $password
     * @param $userId
     * @throws NapbotsAuthException
     * @return Napbots
     */
    public function authenticate($email, $password, $userId): self
    {
        // Set data
        $this->email = $email;
        $this->password = $password;
        $this->userId = $userId;

        // Login to app (get auth token)
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://middle.napbots.com/v1/user/login');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email' => $this->email, 'password' => $this->password]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45000); // 45s timeout
        $response = curl_exec($ch);
        curl_close($ch);

        $json = json_decode($response, true);

        // Napbots not responding
        if (! is_array($json) || ! isset($json['success'])) {
            throw new NapbotsNotResponding();
        }

        // Napbots auth exception
        if ($json['success'] !== true || empty($json['data']) || empty($json['data']['accessToken'])) {
            throw new NapbotsAuthException();
        }

        $this->authToken = $json['data']['accessToken'];

        // Return instance
        return $this;
    }

    /**
     * Get crypto weather.
     * @throws NapbotsNotResponding
     */
    public function getCryptoWeather(): string
    {
        // Get crypto weather
        $tsMax = 0;
        for ($i = 1; $i <= 10; $i++) {
            $weatherApi = file_get_contents('https://middle.napbots.com/v1/crypto-weather');

            if ($weatherApi) {
                $ts = json_decode($weatherApi, true)['data']['weather']['ts'];

                if ($ts > $tsMax) {
                    $weather = json_decode($weatherApi, true)['data']['weather']['weather'];
                    $tsMax = $ts;
                }
            }
            usleep(250000);
        }

        if (empty($weather)) {
            throw new NapbotsNotResponding();
        }

        // Check crypto weather
        if ($weather === 'Extreme markets') {
            return 'extreme';
        } elseif ($weather === 'Mild bull markets') {
            return 'mild_bull';
        } elseif ($weather === 'Mild bear or range markets') {
            return 'mild_bear';
        }

        throw new NapbotsInvalidCryptoWeatherException($weather);
    }

    /**
     * Get exchange infos.
     */
    public function getExchanges()
    {
        // Unauthenticated
        if (empty($this->authToken)) {
            throw new NapbotsUnauthenticated();
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://middle.napbots.com/v1/account/for-user/'.$this->userId);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'token: '.$this->authToken]);
        $response = curl_exec($ch);

        $json = json_decode($response, true);

        // Napbots not responding
        if (! is_array($json) || ! isset($json['success'])) {
            throw new NapbotsNotResponding();
        }

        // Napbots invalid infos
        if (! $json['success'] || empty($json['data']) || ! is_array($json['data'])) {
            throw new NapbotsInvalidInfosException();
        }

        return json_decode($response, true);
    }

    /**
     * @param $allocation
     * @throws NapbotsInvalidInfosException
     * @throws NapbotsNotResponding
     * @throws NapbotsUnauthenticated
     */
    public function setAllocation($allocation)
    {
        // Unauthenticated
        if (empty($this->authToken)) {
            throw new NapbotsUnauthenticated();
        }

        // Resolve config file
        $configFile = app(ConfigFile::class);

        // Rebuild exchange compo
        $params = json_encode([
            'botOnly' => $allocation['bot_only'],
            'compo' => [
                'leverage' => round($allocation['leverage'], 2),
                'compo' => array_map(function ($value) {
                    return round($value, 2);
                }, $allocation['compo']),
            ],
        ]);

        // Get exchange infos
        $exchanges = $this->getExchanges();

        // Foreach exchanges
        foreach ($exchanges['data'] as $exchange) {
            // Ignore exchange
            if (! in_array(strtolower($exchange['exchange']), array_map('strtolower', $configFile->config['ignored_exchanges']))) {
                $nbTries = 0;
                $shouldRetry = false;

                do {
                    // Initialize nbTries
                    $nbTries++;

                    // Change allocation for exchange
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, 'https://middle.napbots.com/v1/account/'.$exchange['accountId']);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'token: '.$this->authToken]);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 45000); // 45s timeout
                    $response = curl_exec($ch);
                    curl_close($ch);

                    // Check errors response
                    $json = json_decode($response, true);

                    if ($json === null || empty($json['success']) || ! $json['success']) {
                        $shouldRetry = true;
                    } else {
                        $shouldRetry = false;
                    }

                    // Check allocations
                    if (! $shouldRetry) {
                        $infos = $this->getExchanges();

                        if (empty($infos['success']) || ! $infos['success'] || empty($infos['data']) || ! is_array($infos['data'])) {
                            $shouldRetry = true;
                        } else {
                            foreach ($infos['data'] as $exchangeCheck) {
                                // If leverage different, set to update
                                if (strval($exchangeCheck['compo']['leverage']) != strval(round($allocation['leverage'], 2))) {
                                    $shouldRetry = true;
                                }

                                // If composition different, set to update
                                if (array_diff($exchangeCheck['compo']['compo'], array_map(function ($value) {
                                    return round($value, 2);
                                }, $allocation['compo']))) {
                                    $shouldRetry = true;
                                }
                            }
                        }
                    }

                    // Show log if retrying
                    if ($shouldRetry && $nbTries < 4) {
                        Log::info('⚙️ Didn\'t work, retrying for exchange...'.$exchange['accountId']);
                    }
                } while ($shouldRetry && $nbTries < 4);

                if ($shouldRetry) {
                    throw new NapbotsNotResponding();
                }

                Log::info('🔨 Changed alloc for exchange '.$exchange['accountId']);
            } else {
                Log::info('✋ Ignored exchange '.$exchange['accountId']);
            }
        }
    }
}
