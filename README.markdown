This plugin provides an easy way to install plugins from a github account (predefined)

## Requirements
- the function "shell_exec()" must be enabled on the server
- git must be installed and configured on the server

## Installation
- Clone from github : in your plugin directory type `git clone git://github.com/josegonzalez/commentable-behavior.git github_plugin`
- Add as a git submodule : in your plugin directory type `git submodule add git://github.com/josegonzalez/commentable-behavior.git github_plugin`
- Download an archive from github and extract it in `/plugins/github_plugin`

## Usage
In command-line, run "cake github_plugin" and follow the prompts