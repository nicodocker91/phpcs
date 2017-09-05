Tools: PHP Code Sniffer
=======================

|  | master | develop |
|----------:|--------------------------------------------------------------------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Pipelines | [![build status](http://git.it.aareonit.fr/docker-dil/tools-phpcs/badges/master/build.svg)](http://git.it.aareonit.fr/docker-dil/tools-phpcs/commits/master) | [![build status](http://git.it.aareonit.fr/docker-dil/tools-phpcs/badges/develop/build.svg)](http://git.it.aareonit.fr/docker-dil/tools-phpcs/commits/develop) |


Contains the image of what Aareon is using to run PHP Code Sniffer tool on its projects.

## Usage

To launch the PHP Code Sniffer into the container, run:

    docker run --rm --user $(id -u):$(id -g) --volume $(pwd):/data/www hub.aareonit.fr:5000/preprod/qa:phpcs <options> ${ROOT_FOLDER}

## Volumes

The only volume available to mount is `/data`.

The entrypoint is `/data/www`.

## Options

Run the following command to read all options available:

    docker run --rm --user $(id -u):$(id -g) --volume $(pwd):/data/www hub.aareonit.fr:5000/preprod/qa:phpcs --help
