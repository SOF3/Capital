PHP = $(shell which php) -dphar.readonly=0

REUSE_MYSQL = false

SUITE_TESTS = suitetest/cases/mysql

.PHONY: all phpstan debug/suite-mysql suitetest $(SUITE_TESTS)

default: phpstan dev/Capital.phar

phpstan: src/SOFe/Capital/Database/RawQueries.php
	$(PHP) vendor/bin/phpstan analyze
phpstan-baseline.neon/clear:
	echo > phpstan-baseline.neon
phpstan-baseline.neon/regenerate: src/SOFe/Capital/Database/RawQueries.php
	$(PHP) vendor/bin/phpstan analyze --generate-baseline

dev/Capital.phar: plugin.yml $(shell find src resources -type f) \
	dev/ConsoleScript.php \
	dev/await-generator.phar dev/await-std.phar dev/libasynql.phar
	$(PHP) dev/ConsoleScript.php --make plugin.yml,src,resources --out $@
	$(PHP) dev/libasynql.phar $@ SOFe\\Capital\\Virions\\$(shell tr -dc A-Za-z </dev/urandom | head -c 16)\\
	$(PHP) dev/await-generator.phar $@ SOFe\\Capital\\Virions\\$(shell tr -dc A-Za-z </dev/urandom | head -c 16)\\
	$(PHP) dev/await-std.phar $@ SOFe\\Capital\\Virions\\$(shell tr -dc A-Za-z </dev/urandom | head -c 16)\\

src/SOFe/Capital/Database/RawQueries.php: dev/libasynql.phar resources/mysql/* resources/sqlite/*
	$(PHP) dev/libasynql.phar fx src/ SOFe\\Capital\\Database\\RawQueries --struct 'final class' --sql resources --prefix capital

dev/ConsoleScript.php: Makefile
	wget -O $@ https://github.com/pmmp/DevTools/raw/master/src/ConsoleScript.php
	touch $@

dev/libasynql.phar: Makefile
	wget -O $@ https://poggit.pmmp.io/v.dl/poggit/libasynql/libasynql/^4.0.1
	touch $@

dev/await-generator.phar: Makefile
	wget -O $@ https://poggit.pmmp.io/v.dl/SOF3/await-generator/await-generator/^3.1.0
	touch $@

dev/await-std.phar: Makefile
	wget -O $@ https://poggit.pmmp.io/v.dl/SOF3/await-std/await-std/^0.2.0
	touch $@

dev/SuiteTester.phar: suitetest/plugin/plugin.yml \
	$(shell find suitetest/plugin/src -type f) \
	dev/ConsoleScript.php \
	dev/await-generator.phar dev/await-std.phar
	$(PHP) dev/ConsoleScript.php --make plugin.yml,src --relative suitetest/plugin/ --out $@
	$(PHP) dev/await-generator.phar $@ SOFe\\SuiteTester\\Virions\\$(shell tr -dc A-Za-z </dev/urandom | head -c 16)\\
	$(PHP) dev/await-std.phar $@ SOFe\\SuiteTester\\Virions\\$(shell tr -dc A-Za-z </dev/urandom | head -c 16)\\

dev/InfoAPI.phar: Makefile
	wget -O $@ https://poggit.pmmp.io/get/InfoAPI
	touch $@

dev/FakePlayer.phar: Makefile
	wget -O $@ https://poggit.pmmp.io/r/146802
	touch $@

suitetest: $(SUITE_TESTS)

$(SUITE_TESTS): dev/Capital.phar dev/FakePlayer.phar dev/InfoAPI.phar dev/SuiteTester.phar
	$(eval CONTAINER_PREFIX := capital-suite-$(shell basename $@))
	docker network create $(CONTAINER_PREFIX)-network || true
	$(REUSE_MYSQL) || docker kill $(CONTAINER_PREFIX)-mysql $(CONTAINER_PREFIX)-pocketmine || true
	$(REUSE_MYSQL) || docker run --rm -d \
		--name $(CONTAINER_PREFIX)-mysql \
		--network $(CONTAINER_PREFIX)-network \
		-e MYSQL_RANDOM_ROOT_PASSWORD=1 \
		-e MYSQL_USER=capital \
		-e MYSQL_PASSWORD=password \
		-e MYSQL_DATABASE=capital_test \
		mysql:8.0
	docker rm $(CONTAINER_PREFIX)-pocketmine || true
	docker create --name $(CONTAINER_PREFIX)-pocketmine \
		--network $(CONTAINER_PREFIX)-network \
		-e SUITE_TESTER_OUTPUT=/data/output.json \
		pmmp/pocketmine-mp:4 \
		start-pocketmine --debug.level=2
	docker cp dev/FakePlayer.phar $(CONTAINER_PREFIX)-pocketmine:/plugins/FakePlayer.phar
	docker cp dev/InfoAPI.phar $(CONTAINER_PREFIX)-pocketmine:/plugins/InfoAPI.phar
	docker cp dev/SuiteTester.phar $(CONTAINER_PREFIX)-pocketmine:/plugins/SuiteTester.phar
	docker cp dev/Capital.phar $(CONTAINER_PREFIX)-pocketmine:/plugins/Capital.phar
	docker cp $@/data $(CONTAINER_PREFIX)-pocketmine:/
	docker cp suitetest/shared/data $(CONTAINER_PREFIX)-pocketmine:/
	$(REUSE_MYSQL) || echo Waiting for MySQL to start...
	$(REUSE_MYSQL) || docker exec $(CONTAINER_PREFIX)-mysql bash -c 'while ! mysqladmin ping -u $$MYSQL_USER -p$$MYSQL_PASSWORD --silent 2>/dev/null; do sleep 1; done'
	$(REUSE_MYSQL) || sleep 5
	docker start -ia $(CONTAINER_PREFIX)-pocketmine
	docker cp $(CONTAINER_PREFIX)-pocketmine:/data/output.json $@/output.json

debug/suite-mysql:
	docker exec -it capital-suite-mysql-mysql bash -c 'mysql -u $$MYSQL_USER -p$$MYSQL_PASSWORD $$MYSQL_DATABASE'
