# SLA

## Requirements

-   PHP 8.1+
-   Twig 3.0+

## Development

1. `python3 -m pip install -r requirements.txt`
2. `fab docker.install`
3. Open in vscode using [Remote containers](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-containers)
4. Open in browser: [http://localhost:5010/](http://localhost:5010/)

## Deployment

1. Use docker or run `fab docker.build` and copy resulting build to server.
2. Don't forget to setup cron script - `php -f src/Application/Public/index.php -- --query /defaults/console/refresh`.
