# Template for composer package

This is a template for a composer package. 
It includes the following features:

- CI Setup with GitHub Actions
  - PHPStan
  - PHPUnit
  - PHP CS Fixer
  - Composer diff
  - Enforced commit conventions
  - Automatic labeling
- Release process using `release-please`
- Dependabot configuration
- Makefile for common tasks (run `make` to display help and list commands)
- Git-hooks to fix code style

## Usage

1. Create a new repository from this template  
`gh repo create --private --clone --template wikando/template-composer-package --remote wikando/your-package-name`  
You can also use the GitHub UI to create a new repository from this template
2. Run `make setup` to setup the repository ruleset and configure a package name
3. (optional) Enable pre-commit hooks: `make git-enable-hooks`
4. Develop your package
5. Push your code and profit from CI
