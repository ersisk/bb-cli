<?php

namespace BBCli\BBCli;

/**
 * BB-CLI Base Class
 *
 * Extending class: Auth, Branch, Pr etc...
 *
 * @see https://bb-cli.github.io/docs/commands
 */
class Base
{
    /**
     * Default command run for actions.
     *
     * Example: 'list'
     */
    const DEFAULT_METHOD = 'DEFAULT_METHOD_NOT_DEFINED';

    /**
     * Defined to custom commands for actions.
     *
     * Example: 'list' => 'list, l'
     */
    const AVAILABLE_COMMANDS = [];

    /**
     * Checks the repo .git folder.
     */
    const CHECK_GIT_FOLDER = true;

    /**
     * Construct
     */
    public function __construct()
    {
        //
    }

    /**
     * Make requests for Bitbucket Rest API.
     *
     * @param  string $method
     * @param  string $url
     * @param  array  $payload
     * @param  bool   $isRepositoryUrl
     * @return mixed
     * @throws \Exception
     * @see    https://developer.atlassian.com/cloud/bitbucket/rest
     */
    public function makeRequest($method = 'GET', $url = '', $payload = [], $isRepositoryUrl = true)
    {
        $this->checkAuth();

        if ($isRepositoryUrl) {
            $repoPath = getRepoPath();
            $url = "/repositories/{$repoPath}{$url}";
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.bitbucket.org/2.0{$url}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic '.base64_encode(userConfig('auth.username').':'.userConfig('auth.appPassword')),
        ]);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            o('Error:' . curl_error($ch));
            die;
        }

        $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpStatusCode < 200 || $httpStatusCode > 299) {
            if ($httpStatusCode === 401) {
                throw new \Exception('Authorization error, please check your credentials.', 1);
            }

            $allowedStatuses = [409];
            if (!in_array($httpStatusCode, $allowedStatuses)) {
                o($result);
                throw new \Exception('An error occurred, status code: '.$httpStatusCode, 1);
            }
        }

        curl_close($ch);

        $jsonResult = json_decode($result, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $result;
        }

        if (array_get($jsonResult, 'type') === 'error') {
            throw new \Exception(array_get($jsonResult, 'error.message'), 1);
        }

        return $jsonResult;
    }

    /**
     * Method name from alias.
     *
     * @param  string $alias
     * @return mixed
     */
    public function getMethodNameFromAlias($alias)
    {
        foreach (static::AVAILABLE_COMMANDS as $method => $methodAliases) {
            $methodAliases = array_map('trim', explode(', ', $methodAliases));

            if (in_array($alias, $methodAliases)) {
                return $method;
            }
        }

        return false;
    }

    /**
     * Lists available commands in shell autocomplete format.
     *
     * @return void
     */
    public function listCommandsForAutocomplete()
    {
        $commands = array_map(function($aliases) {
            return trim(explode(',', $aliases)[0]);
        }, static::AVAILABLE_COMMANDS);

        sort($commands);

        echo implode(' ', $commands);
    }

    /**
     * Checks the auth file.
     * If an error: run bb auth command.
     *
     * @return void
     */
    private function checkAuth()
    {
        if (!userConfig('auth')) {
            o('You have to configure auth info to use this command.', 'red');
            o('Run "bb auth" first.', 'yellow');
            exit(1);
        }
    }
}
