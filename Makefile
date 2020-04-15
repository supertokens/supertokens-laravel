help:
	@echo "        \x1b[33;1mlint: \x1b[0mformat code using php-cs-fixer"
	@echo "  \x1b[33;1mcheck-lint: \x1b[0mcheck formatting of the code"
	@echo "        \x1b[33;1mtest: \x1b[0mruns phpuint"
	@echo "\x1b[33;1mset-up-hooks: \x1b[0mset up various git hooks"
	@echo "        \x1b[33;1mhelp: \x1b[0mshow this"

lint:
	./vendor/bin/php-cs-fixer -v --rules=@PSR1,@PSR2 fix src
	./vendor/bin/php-cs-fixer -v --rules=@PSR1,@PSR2 fix tests

check-lint:
	./vendor/bin/php-cs-fixer -v --dry-run --rules=@PSR1,@PSR2 fix src
	./vendor/bin/php-cs-fixer -v --dry-run --rules=@PSR1,@PSR2 fix tests

set-up-hooks:
	cp hooks/pre-commit.sh .git/hooks/pre-commit
	chmod +x .git/hooks/pre-commit

test:
	./vendor/bin/phpunit