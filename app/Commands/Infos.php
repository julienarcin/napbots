<?php

namespace App\Commands;

use App\Classes\AppFile;
use App\Classes\ConfigFile;
use App\Classes\Napbots;
use App\Exceptions\InvalidConfigFileException;
use App\Exceptions\MissingConfigFileException;
use App\Exceptions\MissingConfigFileFieldException;
use App\Exceptions\NapbotsAuthException;
use App\Exceptions\NapbotsInvalidCryptoWeatherException;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use LaravelZero\Framework\Commands\Command;

class Infos extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'infos';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Get informations about current status.';

    /**
     * Execute the console command.
     *
     * @param Napbots $napbots
     * @param ConfigFile $configFile
     * @param AppFile $appFile
     * @return mixed
     */
    public function handle(Napbots $napbots, ConfigFile $configFile, AppFile $appFile)
    {
        Log::info('💻  Getting napbots infos.');

        $this->alert('Informations');

        try {
            // Get crypto weather
            $weather = $napbots->getCryptoWeather();

            // Authenticate
            $napbots->authenticate($configFile->config['email'], $configFile->config['password'], $configFile->config['user_id']);

            // Get infos
            $infos = $napbots->getExchanges();

            // Crypto weather
            if($weather == 'mild_bear') {
                $this->line('🌧  Current weather is mild-bear or range markets.');
            } elseif($weather == 'mild_bull') {
                $this->line('☀️  Current weather is mild-bull markets.');
            } elseif($weather == 'extreme') {
                $this->line('🌪  Current weather is extreme markets. Trade with prudence.');
            }

            // New line
            $this->newLine();

            // Cooldown infos
            if($appFile->getValue('cooldown_enabled') && $appFile->getValue('cooldown_end') > Carbon::now()->timestamp) {
                $cooldownRemaining = $appFile->getValue('cooldown_end') - Carbon::now()->timestamp;
                $this->line('❄️  Cooldown: Enabled for ' . $cooldownRemaining . ' seconds.');
            } else {
                $this->line('❄️  Cooldown mode: Disabled');
            }

            // New line
            $this->newLine();

            // Exchange infos
            foreach($infos['data'] as $exchange) {
                $this->line('-----------------------------------------');
                $this->line('📈  ' . $exchange['exchangeLabel']);

                // Trading active
                if($exchange['tradingActive']) {
                    $this->line(' - ✅ Trading active.');
                } else {
                    $this->line(' - ❌ Trading inactive.');
                }

                // Portfolio value
                $this->line(' - 💰 Value: $' . $exchange['totalUsdValue'] . ' / ' . $exchange['totalEurValue'] . '€');

                // Portfolio allocation
                $this->line(' - ⚙️  Allocation:');
                $this->line('    * Leverage: ' . $exchange['compo']['leverage']);
                $this->line('    * BotOnly: ' . ($exchange['botOnly'] ? 'true' : 'false'));
                $this->line('    * Composition:');
                foreach($exchange['compo']['compo'] as $key => $value) {
                    $this->line('       ' . $key . ' => ' . $value*100 . '%');
                }
            }
            $this->line('-----------------------------------------');
            $this->newLine();

        } catch(\Exception $exception) {
            $this->error($exception->getMessage());
            die();
        }
    }
}
