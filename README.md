# Venti PrestaShop module

## About

This repository provides a plugin to connect PrestaShop with Ventipay

## Generate module

To generate the module, a zip file must be built following the structure required by PrestaShop, that is, a file named venti.zip containing a folder named venti with all the module’s content. To do this, it is only necessary to run this command inside the repository’s root folder.

mkdir venti && cp -r ./* venti && zip -r venti.zip venti && rm -rf venti
