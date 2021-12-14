.PHONY: all phpstan debug/suite-mysql $(SUITE_TESTS)

PHP_BINARY = $(shell which php)

SUITE_TESTS = $(shell echo suites/*)

default: phpstan dev/Capital.phar

phpstan: src/SOFe/Capital/Database/RawQueries.php
	$(PHP_BINARY) vendor/bin/phpstan analyze
phpstan-baseline.neon/clear:
	echo > phpstan-baseline.neon
phpstan-baseline.neon/regenerate: src/SOFe/Capital/Database/RawQueries.php
	$(PHP_BINARY) vendor/bin/phpstan analyze --generate-baseline

dev/Capital.phar: $(shell find src resources -type f) dev/ConsoleScript.php dev/libasynql.phar dev/await-generator.phar dev/await-std.phar
	$(PHP_BINARY) dev/ConsoleScript.php --make plugin.yml,src,resources --out $@
	$(PHP_BINARY) dev/libasynql.phar $@ SOFe\\Capital\\Virions\\$(shell tr -dc A-Za-z </dev/urandom | head -c 16)\\
	$(PHP_BINARY) dev/await-generator.phar $@ SOFe\\Capital\\Virions\\$(shell tr -dc A-Za-z </dev/urandom | head -c 16)\\
	$(PHP_BINARY) dev/await-std.phar $@ SOFe\\Capital\\Virions\\$(shell tr -dc A-Za-z </dev/urandom | head -c 16)\\

src/SOFe/Capital/Database/RawQueries.php: dev/libasynql.phar resources/mysql/* resources/sqlite/*
	$(PHP_BINARY) dev/libasynql.phar fx src/ SOFe\\Capital\\Database\\RawQueries --struct 'final class' --sql resources --prefix capital

dev/ConsoleScript.php: Makefile
	wget -O $@ https://github.com/pmmp/DevTools/raw/master/src/ConsoleScript.php
	touch $@

dev/libasynql.phar: Makefile
	wget -O $@ https://poggit.pmmp.io/v.dl/poggit/libasynql/libasynql/^4.0.0
	touch $@

dev/await-generator.phar: Makefile
	wget -O $@ https://poggit.pmmp.io/v.dl/SOF3/await-generator/await-generator/^3.1.0
	touch $@

dev/await-std.phar: Makefile
	wget -O $@ https://poggit.pmmp.io/v.dl/SOF3/await-std/await-std/^0.2.0
	touch $@

dev/InfoAPI.phar: Makefile
	wget -O $@ https://poggit.pmmp.io/get/InfoAPI
	touch $@

dev/FakePlayer.phar: Makefile
	wget -O $@ https://poggit.pmmp.io/r/146802
	touch $@

suites: $(SUITE_TESTS)

$(SUITE_TESTS): dev/Capital.phar dev/InfoAPI.phar dev/FakePlayer.phar
	docker network create capital-suite-network || true
	docker kill capital-suite-mysql capital-suite-pocketmine || true
	docker run --rm -d \
		--name capital-suite-mysql \
		--network capital-suite-network \
		-e MYSQL_RANDOM_ROOT_PASSWORD=1 \
		-e MYSQL_USER=capital \
		-e MYSQL_PASSWORD=password \
		-e MYSQL_DATABASE=capital_test \
		mysql:8.0
	docker create --rm --name capital-suite-pocketmine \
		--network capital-suite-network \
		pmmp/pocketmine-mp:latest
	docker cp dev/FakePlayer.phar capital-suite-pocketmine:/plugins/FakePlayer.phar
	docker cp dev/InfoAPI.phar capital-suite-pocketmine:/plugins/InfoAPI.phar
	docker cp dev/Capital.phar capital-suite-pocketmine:/plugins/Capital.phar
	docker cp $@/data/plugin_data capital-suite-pocketmine:/data/plugin_data
	echo Waiting for MySQL to start...
	docker exec capital-suite-mysql bash -c 'while ! mysqladmin ping -u $$MYSQL_USER -p$$MYSQL_PASSWORD --silent 2>/dev/null; do sleep 1; done'
	sleep 5
	docker start -ia capital-suite-pocketmine

debug/suite-mysql:
	docker exec -it capital-suite-mysql bash -c 'mysql -u $$MYSQL_USER -p$$MYSQL_PASSWORD $$MYSQL_DATABASE'
