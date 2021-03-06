<?php
namespace Williamson\TPLinkSmartplug;

use UnexpectedValueException;

class TPLinkDevice
{

    protected $config;
    protected $deviceName;
    protected $client;


    /**
     * TPLinkDevice constructor.
     * @param array $config
     * @param string $deviceName
     */
    public function __construct(array $config, $deviceName)
    {
        $this->config = $config;
        $this->deviceName = $deviceName;
    }

    /**
     * Toggle the current status of the switch on/off
     *
     * @return string
     */
    public function togglePower()
    {
        $status = (bool)json_decode($this->sendCommand(TPLinkCommand::systemInfo()))->system->get_sysinfo->relay_state;

        return $status ? $this->sendCommand(TPLinkCommand::powerOff()) : $this->sendCommand(TPLinkCommand::powerOn());
    }

    /**
     * Send a command to the connected device.
     *
     * @param array $command
     * @return mixed|string
     */
    public function sendCommand(array $command)
    {
        $this->connectToDevice();

        if (fwrite($this->client, $this->encrypt(json_encode($command))) === false) {
            return $this->connectionError();
        }

        $response = $this->decrypt(stream_get_contents($this->client));
        $this->disconnect();

        return $response;
    }

    /**
     * Connect to the specified device
     */
    protected function connectToDevice()
    {
        $this->client = stream_socket_client(
            "tcp://" . $this->getConfig("ip") . ":" . $this->getConfig("port"),
            $errorNumber,
            $errorMessage,
            5
        );

        if ($this->client === false) {
            throw new UnexpectedValueException("Failed to connect to {$this->deviceName}: $errorMessage ($errorNumber)");
        }
    }

    /**
     * @param $key
     * @param null $default
     * @return mixed
     */
    protected function getConfig($key, $default = null)
    {
        if (is_array($this->config) && isset($this->config[$key])) {
            return $this->config[$key];
        } else {
            return $default;
        }
    }

    /**
     * Encrypt all data being sent to the device
     *
     * @param $string
     * @return mixed
     */
    protected function encrypt($string)
    {
        $key = 171;

        return collect(str_split($string))
            ->reduce(function ($result, $character) use (&$key) {
                $key = $key ^ ord($character);

                return $result .= chr($key);
            },
                "\0\0\0\0");
    }

    /**
     *
     * @return string
     */
    protected function connectionError()
    {
        return json_encode([
            'success' => false,
            'message' => "When sending the command to the smartplug {$this->deviceName}, the connection terminated before the command was sent.",
        ]);
    }

    /**
     * Decrypt the response from the device.
     *
     * Must ignore the first 4 bytes of the response to decrypt properly.
     * @param $data
     * @return mixed
     */
    protected function decrypt($data)
    {
        $key = 171;

        return collect(str_split(substr($data, 4)))
            ->reduce(function ($result, $character) use (&$key) {
                $a = $key ^ ord($character);
                $key = ord($character);

                return $result .= chr($a);
            });
    }

    /**
     * Disconnect from the device
     */
    protected function disconnect()
    {
        if (isset($this->client) && is_resource($this->client)) {
            fclose($this->client);
        }
    }
}
