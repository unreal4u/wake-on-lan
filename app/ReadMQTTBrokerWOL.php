<?php

declare(strict_types=1);

namespace unreal4u\WOLBigpapa;

use unreal4u\rpiCommonLibrary\Base;
use unreal4u\rpiCommonLibrary\JobContract;
use unreal4u\MQTT\DataTypes\Message;
use unreal4u\MQTT\DataTypes\TopicFilter;

class ReadMQTTBrokerWOL extends Base {
    /**
     * Will be executed once before running the actual job
     *
     * @return JobContract
     */
    public function setUp(): JobContract
    {
        return $this;
    }

    public function configure()
    {
        $this
            ->setName('wol:bigpapa')
            ->setDescription('Subscribes to MQTT and writes incoming commands to another topic')
            ->setHelp('TODO')
        ;
    }

    /**
     * Runs the actual job that needs to be executed
     *
     * @return bool Returns true if job was successful, false otherwise
     */
    public function runJob(): bool
    {
        $mqttCommunicator = $this->communicationsFactory('MQTT');
        $topicFilter = new TopicFilter('commands/bigpapa');
	$mqttCommunicator->subscribeToTopic($topicFilter, function(Message $message) use ($mqttCommunicator) {
            if ($message->getPayload() === 'on') {
                $this->wakeOnLan(BROADCAST_NETWORK, MAC_ADDRESS);
                $mqttCommunicator->sendMessage('telemetry/bigpapa', json_encode([
                    'time' => new \DateTimeImmutable(),
		    'status' => 'pending on',
                ]));
	    }
        });
        return true;
    }

    private function wakeOnLan(string $broadcast, string $mac): bool
    {
        $hardwareAddress = pack('H*', preg_replace('/[^0-9a-fA-F]/', '', $mac));
        $packet = sprintf(
            '%s%s',
            str_repeat(chr(255), 6),
            str_repeat($hardwareAddress, 16)
        );

        $return = false;
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        if ($socket !== false) {
            $options = socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, true);

            if ($options !== false) {
                socket_sendto($socket, $packet, strlen($packet), 0, $broadcast, 7);
                socket_close($socket);
                $return = true;
            }
        }

        return $return;
    }

    /**
     * If method runJob returns false, this will return an array with errors that may have happened during execution
     *
     * @return \Generator
     */
    public function retrieveErrors(): \Generator
    {
        yield '';
    }

    /**
     * The number of seconds after which this script should kill itself
     *
     * @return int
     */
    public function forceKillAfterSeconds(): int
    {
        return 3600;
    }

    /**
     * The loop should run after this amount of microseconds (1 second === 1000000 microseconds)
     *
     * @return int
     */
    public function executeEveryMicroseconds(): int
    {
        return 0;
    }
}
