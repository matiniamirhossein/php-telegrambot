<?php
namespace TelegramBot;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use TelegramBot\Exception\TelegramBotException;
use function array_key_exists;
use function in_array;
use function strtolower;

/**
 * Blocking
 *
 * @method Array getMe()
 * @method Array getChat($args)
 * @method Array sendMessage($args)
 * @method Array sendLocation($args)
 * @method Array getUpdates($args = [])
 * @method Array sendPhoto($args)
 * @method Array sendVideo($args)
 * @method Array sendVoice($args)
 * @method Array sendDocument($args)
 * @method Array getChatMember($args)
 * @method Array getChatMembersCount($args)
 * @method Array getChatAdministrators($args)
 * @method Array leaveChat($args)
 * @method Array exportChatInviteLink($args)
 * @method Array sendChatAction($args)
 * @method Array getUserProfilePhotos($args)
 * @method Array answerCallbackQuery($args)
 * @method Array deleteMessage($args)
 * @method Array editMessageText($args)
 * @method Array editMessageCaption($args)
 * @method Array editMessageReplyMarkup($args)
 *
 *
 * Async
 *
 * @method \GuzzleHttp\Promise\PromiseInterface getMeAsync()
 * @method \GuzzleHttp\Promise\PromiseInterface sendMessageAsync($args)
 * @method \GuzzleHttp\Promise\PromiseInterface sendLocationAsync($args)
 * @method \GuzzleHttp\Promise\PromiseInterface getUpdatesAsync($args = [])
 * @method \GuzzleHttp\Promise\PromiseInterface sendPhotoAsync($args)
 * @method \GuzzleHttp\Promise\PromiseInterface sendVideoAsync($args)
 * @method \GuzzleHttp\Promise\PromiseInterface sendVoiceAsync($args)
 * @method \GuzzleHttp\Promise\PromiseInterface sendDocumentAsync($args)
 * @method \GuzzleHttp\Promise\PromiseInterface getChatMemberAsync($args)
 * @method \GuzzleHttp\Promise\PromiseInterface getChatMembersCountAsync($args)
 * @method \GuzzleHttp\Promise\PromiseInterface getChatAdministratorsAsync($args)
 * @method \GuzzleHttp\Promise\PromiseInterface leaveChatAsync($args)
 * @method \GuzzleHttp\Promise\PromiseInterface exportChatInviteLinkAsync($args)
 * @method \GuzzleHttp\Promise\PromiseInterface sendChatActionAsync($args)
 * @method \GuzzleHttp\Promise\PromiseInterface getUserProfilePhotosAsync($args)
 * @method \GuzzleHttp\Promise\PromiseInterface answerCallbackQueryAsync($args)
 * @method \GuzzleHttp\Promise\PromiseInterface deleteMessageAsync($args)
 * @method \GuzzleHttp\Promise\PromiseInterface editMessageTextAsync($args)
 * @method \GuzzleHttp\Promise\PromiseInterface editMessageCaptionAsync($args)
 * @method \GuzzleHttp\Promise\PromiseInterface editMessageReplyMarkupAsync($args)
 */
class Bot
{
    private $apiEndpoint = 'https://api.telegram.org/bot<token>/';
    private $httpClient;
    private $me;
    private $ignoreErrors;

    /**
     * Bot constructor.
     * @param $token
     * @throws Exception
     */
    public function __construct($token, $ignoreErrors = false)
    {
        $this->ignoreErrors = $ignoreErrors;
        $this->apiEndpoint = str_replace('<token>', $token, $this->apiEndpoint);
        $this->httpClient = new Client([
            'base_uri' => $this->apiEndpoint,
            'verify' => dirname(dirname(__DIR__)) . '/schema/cacert.pem',
            'http_errors' => false,
        ]);
        try {
            $this->me = $this->getMe();
        } catch (TelegramBotException $e) {
            throw new Exception("Unable to connect to telegram bot api. Maybe token is invalid. Error: " . $e->getMessage());
        }
    }

    public function __call($name, $arguments)
    {
        if ($name == 'getMe' && $this->me) {
            return $this->me;
        }
        if (sizeof($arguments) == 1 && is_array($arguments[0])) {
            $arguments = $arguments[0];
        }
        $async = strtolower(substr($name, -5)) == 'async';
        if ($async) {
            $name = substr($name, 0, strlen($name) - 5);
        }
        $ignoreErrors = isset($arguments['ignoreErrors']) ? $arguments['ignoreErrors'] : $this->ignoreErrors;
        try {
            return $this->request($name, $arguments, $async);
        } catch (Exception $e) {
            if (!$ignoreErrors) {
                throw $e;
            }
        }
    }

    /**
     * @param $method
     * @param $args
     * @param bool $async
     * @return mixed
     * @throws TelegramBotException
     */
    public function request($method, $args, $async = false)
    {
        $options = [];
        if (sizeof($args)) {
            $options['multipart'] = [];
            $multipart = ['audio', 'photo', 'video', 'document', 'voice', 'video_note', 'sticker', 'png_sticker', 'certificate'];
            foreach ($args as $key => $arg) {
                if (is_array($arg)) {
                    $arg = \json_encode($arg);
                }
                $options['multipart'][] = ['name' => $key, 'contents' => $arg];
            }
        }
        try {
            $response = $async ? $this->httpClient->postAsync($method, $options) : $this->httpClient->post($method, $options);
            if ($async) {
                return $response;
            }
            $body = \GuzzleHttp\json_decode($response->getBody()->getContents(), true);
            if (in_array($response->getStatusCode(), [400, 403, 404, 409])) {
                throw new TelegramBotException($body['description'], $response->getStatusCode(), $method, $args);
            }
            return $body['result'];
        } catch (ClientException $e) {
            throw new TelegramBotException("Unable to make request: {$e->getMessage()}", 0, $method, $args, $e);
        }
    }

    public function detect_message_type(array $message)
    {
        $keyVal = [
            'text', 'photo', 'video', 'voice', 'sticker', 'video_note', 'document', 'audio', 'contact', 'location', 'venue'
        ];
        foreach ($keyVal as $key) {
            if (array_key_exists($key, $message)) {
                return $key;
            }
        }
        return null;
    }

    public function splitTextIntoParts($text)
    {
        $arr = [];
        $parts = ceil(strlen($text) / 4096) - 1;
        for ($i = 0; $i <= $parts; ++$i) {
            $arr[$i] = substr($text, ($i * 4096), 4096);
        }
        return $arr;
    }

    public function parseCommand($text)
    {
        preg_match("%^\/([^@\s]+)@?(?:(\S+)|)\s?([\s\S]*)$%i", $text, $matches);
        if (!isset($matches[1]) || empty($matches[1])) {
            return false;
        }
        $command = $matches[1];
        if (empty($matches[3])) {
            $args = [];
        } else {
            $args = explode(" ", $matches[3]);
        }
        return ['name' => $command, 'args' => $args, 'args_text' => $matches[3]];
    }
}