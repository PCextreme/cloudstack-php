<?php

declare(strict_types=1);

namespace PCextreme\Cloudstack\Console;

use PCextreme\Cloudstack\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class ApiListCommand extends Command
{
    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('api:list')
            ->setDescription('Generate API list cache.')
            ->setHelp("This will generate the API list cache file usign the 'listApis' command.");
    }

    /**
     * Executes the current command.
     *
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output) : void
    {
        $urlApi    = $this->askUrlApi($input, $output);
        $apiKey    = $this->askApiKey($input, $output);
        $secretKey = $this->askSecretKey($input, $output);

        $output->writeLn('');
        $output->writeLn("<info>Processing API list. Please Wait...</info>");

        $client = new Client([
            'urlApi'    => $urlApi,
            'apiKey'    => $apiKey,
            'secretKey' => $secretKey,
        ]);

        $command = 'listApis';
        $method  = $client->getCommandMethod($command);
        $url     = $client->getCommandUrl($command, []);
        $request = $client->getRequest($method, $url, []);

        $list = $client->getResponse($request);

        // We expect an array from the getResponse method, when this returns
        // with a string we most likely got a server error.
        if (is_string($list)) {
            throw new \RuntimeException(sprintf(
                "Invalid API response, received: %s",
                $list
            ));
        }

        $this->processList($output, $list);
    }

    /**
     * Ask for URL API.
     *
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return string
     */
    protected function askUrlApi(InputInterface $input, OutputInterface $output) : string
    {
        $question = new Question(
            'Enter target API url [default: https://api.auroracompute.eu/ams]: ',
            'https://api.auroracompute.eu/ams'
        );

        return $this->getHelper('question')->ask($input, $output, $question);
    }

    /**
     * Ask for API key.
     *
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return string
     */
    protected function askApiKey(InputInterface $input, OutputInterface $output) : string
    {
        $question = (new Question('Enter API key: '))
            ->setValidator(function ($answer) {
                if (is_null($answer)) {
                    throw new \InvalidArgumentException("API key can't be null.");
                }

                return $answer;
            })
            ->setMaxAttempts(2);

        return $this->getHelper('question')->ask($input, $output, $question);
    }

    /**
     * Ask for secret key.
     *
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return string
     */
    protected function askSecretKey(InputInterface $input, OutputInterface $output) : string
    {
        $question = (new Question('Enter secret key: '))
            ->setValidator(function ($answer) {
                if (is_null($answer)) {
                    throw new \InvalidArgumentException("Secret key can't be null.");
                }

                return $answer;
            })
            ->setMaxAttempts(2);

        return $this->getHelper('question')->ask($input, $output, $question);
    }

    /**
     * Dump cache file of APIs list.
     *
     * @param  OutputInterface $output
     * @param  array           $list
     * @return void
     */
    protected function processList(OutputInterface $output, array $list = []) : void
    {
        if (empty($list)) {
            throw new \RuntimeException("API list is empty.");
        }

        $progress = new ProgressBar($output, $list['listapisresponse']['count']);
        $progress->start();

        $commands = [];

        foreach ($list['listapisresponse']['api'] as $api) {
            $commandName = $api['name'];
            $command = [
                'description' => $api['description'],
                'isasync'     => $api['isasync'],
            ];

            $commandParams = [];

            foreach ($api['params'] as $apiParam) {
                $paramName = $apiParam['name'];
                $param = [
                    'description' => $apiParam['description'],
                    'required' => $apiParam['required'],
                    'type' => $apiParam['type'],
                ];

                $commandParams[$paramName] = $param;
            }

            $command['params'] = $commandParams;

            $commands[$commandName] = $command;

            $progress->advance();
        }

        $listFile = "<?php\n\n";
        $listFile .= "// api_list.php @generated by api:list command\n\n";
        $listFile .= "return ".var_export($commands, true).";\n";

        file_put_contents(__DIR__ . '/../../cache/api_list.php', $listFile);

        $progress->finish();
    }
}
