This demo features a single tool to get the current time in a given timezone.

To run `demo/openai.php` you must set the `OPENAI_API_KEY` environment variable.
To run `demo/ollama-hermes2pro.php` you can set `OLLAMA_HOST` if you want to use a host different than the default `http://localhost:11434`.

```console
$ php demo/ollama-hermes2pro.php
^ "The current time in France (Paris) is 12:59:40, and the current time in New York City is 07:59:40."

$ php demo/openai.php
^ "It is currently 12:59:46 in France and 07:59:46 in New York City."

$ php demo/openai.php germany and korea
^ "The current time in Germany is 12:59:57 and the current time in Korea is 20:59:57."

$ php demo/ollama-hermes2pro.php germany and korea
^ """
In Europe, it is currently 13:00 for Berlin, Germany.\n
And in Asia, it is currently 21:00 for Seoul, South Korea.
"""

$ php demo/ollama-hermes2pro.php germany and korea
^ "The current time in Germany (Berlin) is 13:00 and in Korea (Seoul) is 21:00."
```

You can also print requests/responses using the `-v` flag:
```console
$ php demo/ollama-hermes2pro.php -v
[2024-03-29T12:12:46.058826+00:00] http.INFO: Sending request: POST http://ai-mbp-14:11434/v1/chat/completions HTTP/1.1 {"model":"adrienbrault/nous-hermes2pro:Q4_K_M","messages":[{"role":"system","content":"You are a function calling AI model. You are provided with function signatures within <tools></tools> XML tags. You may call one or more functions to assist with the user query. Don't make assumptions about what values to plug into functions. Here are the available tools: <tools> [{\"type\":\"function\",\"function\":{\"name\":\"get_current_time\",\"parameters\":{\"type\":\"object\",\"properties\":{\"timezone\":{\"type\":\"string\",\"description\":\"The timezone to get the current time for. Example: \\\"America\\/Los_Angeles\\\" or \\\"Asia\\/Tokyo\\\"\"}},\"required\":[\"timezone\"]}}}] </tools> Use the following json schema for each tool call you will make: {\"type\": \"object\", \"properties\": {\"name\": {\"title\": \"Name\", \"type\": \"string\"}, \"arguments\": {\"title\": \"Arguments\", \"type\": \"object\"}}, \"required\": [\"arguments\", \"name\"], \"title\": \"FunctionCall\"} For each function call return a json object with function name and arguments within <tool_call></tool_call> XML tags as follows: <tool_call> {\"name\": <function-name>, \"arguments\": <args-dict>} </tool_call>. Only reply with <tool_call>...</tool_call>, no other content. Answer the user question succinctly."},{"role":"user","content":"What time is it in france? What about new york city?"}]} {"request":{"GuzzleHttp\\Psr7\\Request":[]},"uid":"6606b03e0e5611.72535688"} []
[2024-03-29T12:12:47.863957+00:00] http.INFO: Received response: HTTP/1.1 200 OK {"id":"chatcmpl-746","object":"chat.completion","created":1711714367,"model":"adrienbrault/nous-hermes2pro:Q4_K_M","system_fingerprint":"fp_ollama","choices":[{"index":0,"message":{"role":"assistant","content":"\u003ctool_call\u003e {\"name\": \"get_current_time\", \"arguments\": {\"timezone\": \"Europe\\/Paris\"}} \u003c/tool_call\u003e\n\u003ctool_call\u003e {\"name\": \"get_current_time\", \"arguments\": {\"timezone\": \"America\\/New_York\"}} \u003c/tool_call\u003e"},"finish_reason":"stop"}],"usage":{"prompt_tokens":0,"completion_tokens":72,"total_tokens":72}}  {"milliseconds":1805,"uid":"6606b03e0e5611.72535688"} []
[2024-03-29T12:12:47.872500+00:00] http.INFO: Sending request: POST http://ai-mbp-14:11434/v1/chat/completions HTTP/1.1 {"model":"adrienbrault/nous-hermes2pro:Q4_K_M","messages":[{"role":"system","content":"You are a function calling AI model. You are provided with function signatures within <tools></tools> XML tags. You may call one or more functions to assist with the user query. Don't make assumptions about what values to plug into functions. Here are the available tools: <tools> [{\"type\":\"function\",\"function\":{\"name\":\"get_current_time\",\"parameters\":{\"type\":\"object\",\"properties\":{\"timezone\":{\"type\":\"string\",\"description\":\"The timezone to get the current time for. Example: \\\"America\\/Los_Angeles\\\" or \\\"Asia\\/Tokyo\\\"\"}},\"required\":[\"timezone\"]}}}] </tools> Use the following json schema for each tool call you will make: {\"type\": \"object\", \"properties\": {\"name\": {\"title\": \"Name\", \"type\": \"string\"}, \"arguments\": {\"title\": \"Arguments\", \"type\": \"object\"}}, \"required\": [\"arguments\", \"name\"], \"title\": \"FunctionCall\"} For each function call return a json object with function name and arguments within <tool_call></tool_call> XML tags as follows: <tool_call> {\"name\": <function-name>, \"arguments\": <args-dict>} </tool_call>. Only reply with <tool_call>...</tool_call>, no other content. Answer the user question succinctly."},{"role":"user","content":"What time is it in france? What about new york city?"},{"role":"assistant","content":"<tool_call>\n{\"name\":\"get_current_time\",\"arguments\":{\"timezone\":\"Europe\\/Paris\"}}\n</tool_call>\n<tool_call>\n{\"name\":\"get_current_time\",\"arguments\":{\"timezone\":\"America\\/New_York\"}}\n</tool_call>"},{"role":"system","content":"<tool_response>13:12:47</tool_response>"},{"role":"system","content":"<tool_response>08:12:47</tool_response>"}]} {"request":{"GuzzleHttp\\Psr7\\Request":[]},"uid":"6606b03fd50203.07414593"} []
[2024-03-29T12:12:49.026381+00:00] http.INFO: Received response: HTTP/1.1 200 OK {"id":"chatcmpl-524","object":"chat.completion","created":1711714368,"model":"adrienbrault/nous-hermes2pro:Q4_K_M","system_fingerprint":"fp_ollama","choices":[{"index":0,"message":{"role":"assistant","content":"The current time in France (Paris) is 13:12:47. The current time in New York City is 08:12:47."},"finish_reason":"stop"}],"usage":{"prompt_tokens":118,"completion_tokens":39,"total_tokens":157}}  {"milliseconds":1154,"uid":"6606b03fd50203.07414593"} []
^ "The current time in France (Paris) is 13:12:47. The current time in New York City is 08:12:47."
```