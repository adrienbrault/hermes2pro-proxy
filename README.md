[NousResearch/Hermes-2-Pro-Mistral-7B][hf_url] is an open source LLM with great function calling capabilities.

This [PSR-18][psr18] plugin converts OpenAI API requests/responses that use [function calling][oa_fc] to and from [the Hermes2Pro prompt format][hf_url_pf].

It lets you use Hermes2Pro function calling with the same OpenAI api client code that you currently use with `api.openai.com`.

Install [ollama][ollama] ([docker][ollama_docker]) and pull the model:

```console
$ ollama pull adrienbrault/nous-hermes2pro:Q4_K_M
```

Install [this package][packagist]:
```console
$ composer require adrienbrault/hermes2pro@dev
```

Then update your code to use the plugin:
```php
use AdrienBrault\Hermes2Pro\Hermes2ProPlugin;
use Http\Client\Common\PluginClient;
use Http\Discovery\Psr18ClientDiscovery;

require_once __DIR__ . '/vendor/autoload.php';

$client = OpenAI::factory()
    ->withApiKey(getenv('OPENAI_API_KEY'))
    ->withBaseUri(getenv('OLLAMA_HOST') ?: 'http://localhost:11434/v1')
    ->withHttpClient(
        new PluginClient(
            Psr18ClientDiscovery::find(),
            [
                new Hermes2ProPlugin()
            ]
        )
    )
    ->make()
;
```

Then use `adrienbrault/nous-hermes2pro:Q4_K_M` as the `model` in your chat requests.
There are [more tags/quantizations available][ollama_url].

[hf_url]: https://huggingface.co/NousResearch/Hermes-2-Pro-Mistral-7B
[hf_url_pf]: https://huggingface.co/NousResearch/Hermes-2-Pro-Mistral-7B#prompt-format-for-function-calling
[oa_fc]: https://platform.openai.com/docs/guides/function-calling
[ollama]: https://ollama.com/
[ollama_url]: https://ollama.com/adrienbrault/nous-hermes2pro/tags
[ollama_docker]: https://ollama.com/blog/ollama-is-now-available-as-an-official-docker-image
[psr18]: https://www.php-fig.org/psr/psr-18/
[packagist]: https://packagist.org/packages/adrienbrault/hermes2pro