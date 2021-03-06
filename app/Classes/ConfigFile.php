<?php

namespace App\Classes;

use Illuminate\Support\Facades\Storage;
use App\Exceptions\InvalidConfigFileException;
use App\Exceptions\MissingConfigFileException;
use App\Exceptions\InvalidConfigFileFieldException;
use App\Exceptions\MissingConfigFileFieldException;
use App\Exceptions\InvalidConfigFileCompositionException;

/**
 * Class ConfigFile.
 */
class ConfigFile
{
    /**
     * @var
     */
    public $config;

    /**
     * ConfigFile constructor.
     * @throws InvalidConfigFileException
     * @throws MissingConfigFileException
     */
    public function __construct()
    {
        if (! Storage::exists('config.json')) {
            throw new MissingConfigFileException();
        }

        $file = Storage::get('config.json');
        $decoded = json_decode($file, true);

        if (empty($decoded) || ! is_array($decoded)) {
            throw new InvalidConfigFileException();
        }

        $this->config = $decoded;

        // Return instance
        return $this;
    }

    /**
     * @throws MissingConfigFileFieldException
     * @throws InvalidConfigFileFieldException
     */
    public function checkFile()
    {
        // Check email
        if (empty($this->config['email'])) {
            throw new MissingConfigFileFieldException('email');
        }

        // Check password
        if (empty($this->config['password'])) {
            throw new MissingConfigFileFieldException('password');
        }

        // Check user id
        if (empty($this->config['user_id']) || $this->config['user_id'] == 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx') {
            throw new MissingConfigFileFieldException('user_id');
        }

        // Check telegram token
        if (! isset($this->config['telegram_token'])) {
            throw new MissingConfigFileFieldException('telegram_token');
        }

        // Check telegram chat ids
        if (! isset($this->config['telegram_chat_ids']) || ! is_array($this->config['telegram_chat_ids'])) {
            throw new MissingConfigFileFieldException('telegram_chat_ids');
        }

        // Check cron ping url
        if (! isset($this->config['cron_ping_url'])) {
            throw new MissingConfigFileFieldException('cron_ping_url');
        }

        // Check ignored exchanges
        if (! isset($this->config['ignored_exchanges']) || ! is_array($this->config['ignored_exchanges'])) {
            throw new MissingConfigFileFieldException('ignored_exchanges');
        }

        // Check allocations
        if (empty($this->config['allocations'])) {
            throw new MissingConfigFileFieldException('allocations');
        }

        // Check allocations mild bear
        if (empty($this->config['allocations']['mild_bear'])) {
            throw new MissingConfigFileFieldException('allocations -> mild_bear');
        }

        // Check allocations mild bull
        if (empty($this->config['allocations']['mild_bull'])) {
            throw new MissingConfigFileFieldException('allocations -> mild_bull');
        }

        // Check allocations extreme
        if (empty($this->config['allocations'][/**/'extreme'])) {
            throw new MissingConfigFileFieldException('allocations -> extreme');
        }

        // Check weather change cooldown
        if (empty($this->config['weather_change_cooldown'])) {
            throw new MissingConfigFileFieldException('weather_change_cooldown');
        }

        // Check weather change cooldown enabled field
        if (! isset($this->config['weather_change_cooldown']['enabled']) || ! is_bool($this->config['weather_change_cooldown']['enabled'])) {
            throw new MissingConfigFileFieldException('weather_change_cooldown -> enabled');
        }

        // Check weather change cooldown enabled field
        if (! isset($this->config['weather_change_cooldown']['enabled']) || ! is_bool($this->config['weather_change_cooldown']['enabled'])) {
            throw new MissingConfigFileFieldException('weather_change_cooldown -> enabled');
        }

        // Check weather change cooldown duration seconds field
        if (! isset($this->config['weather_change_cooldown']['duration_seconds']) || ! is_int($this->config['weather_change_cooldown']['duration_seconds'])) {
            throw new MissingConfigFileFieldException('weather_change_cooldown -> duration_seconds');
        }

        // Check weather change cooldown allocation field
        if (! isset($this->config['weather_change_cooldown']['allocation'])) {
            throw new MissingConfigFileFieldException('weather_change_cooldown -> allocation');
        }

        // Check allocation compositions
        $this->checkAllocation('mild_bear', $this->config['allocations']['mild_bear']);
        $this->checkAllocation('mild_bull', $this->config['allocations']['mild_bull']);
        $this->checkAllocation('extreme', $this->config['allocations']['extreme']);
        $this->checkAllocation('weather_change_cooldown', $this->config['weather_change_cooldown']['allocation']);

        // Return instance
        return $this;
    }

    /**
     * @param $name
     * @param $allocation
     * @throws InvalidConfigFileFieldException
     * @throws MissingConfigFileFieldException
     * @throws InvalidConfigFileCompositionException
     * @return ConfigFile
     */
    public function checkAllocation($name, $allocation)
    {
        // Check bot only field
        if (! isset($allocation['bot_only']) || ! is_bool($allocation['bot_only'])) {
            throw new MissingConfigFileFieldException('allocations -> '.$name.' -> bot_only');
        }

        // Check leverage field
        if (! isset($allocation['leverage'])) {
            throw new MissingConfigFileFieldException('allocations -> '.$name.' -> leverage');
        }

        // Check leverage validity
        if (round($allocation['leverage'], 2) < 0 || round($allocation['leverage'], 2) > 1.5) {
            throw new InvalidConfigFileFieldException('allocations -> '.$name.' -> bot_only');
        }

        // Check compo field
        if (! isset($allocation['compo'])) {
            throw new MissingConfigFileFieldException('allocations -> '.$name.' -> compo');
        }

        // Check compo validity
        $sumWeights = 0;
        foreach ($allocation['compo'] as $botWeight) {
            $sumWeights += round($botWeight, 2);
        }

        if ($sumWeights != 1) {
            throw new InvalidConfigFileCompositionException($name);
        }

        // Return instance
        return $this;
    }
}
