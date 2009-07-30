This plugin provides an easy way to install plugins from a github account (predefined)

## Requirements
- the function "shell_exec()" must be enabled on the server
- git must be installed and configured on the server

## Installation
- Clone from github : in your plugin directory type `git clone git://github.com/josegonzalez/commentable-behavior.git github_plugin`
- Add as a git submodule : in your plugin directory type `git submodule add git://github.com/josegonzalez/commentable-behavior.git github_plugin`
- Download an archive from github and extract it in `/plugins/github_plugin`

## Features
- List installed plugins
- View available plugins
- Search available plugins
- Install plugin as a git submodule
- Install plugin from a zip archive via github
- Update all git plugin submodules
- Update a specific git plugin submodule

## Usage
In command-line, run "cake github_plugin" and follow the prompts

## TODO
- Installing from plugin archive cache where possible
- Moving cache files to their own folder
- Configuring the github user with which the plugin interacts with
- Install plugin from a specified archive
- Install plugin from a search
- Browse available plugins
- Updating plugins installed via archive
- Updating plugin from an archive install to a git submodule install
- Removing plugin installed as submodule
- Remove plugin installed as zip archive
- Copy plugin from another project
- Archive an installed plugin
- Upload an installed plugin to some server
- Interface with the thoughtglade plugin server
- Provide the ability to archive all plugins at will
- Retain metadata for each plugin in it's installation folder