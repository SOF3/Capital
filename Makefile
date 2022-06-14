PHP = $(shell which php) -dphar.readonly=0
COMPOSER = dev/composer.phar

REUSE_MYSQL = false

SUITE_TESTS = $(shell echo suitetest/cases/*)

POCKETMINE_VERSION = 4

DIFF = diff -y --suppress-common-lines --width=$(shell tput cols)

SUITE_TESTS_CONFIG_REGEN = false

CAPITAL_SOURCE_FILES = plugin.yml $(shell find src resources -type f)
CAPITAL_VIRIONS = dev/await-generator.phar dev/await-std.phar dev/libasynql.phar dev/rwlock.phar

.PHONY: all phpstan fmt debug/suite-mysql suitetest $(SUITE_TESTS)

default: phpstan dev/Capital.phar

phpstan: src/SOFe/Capital/Database/RawQueries.php vendor
	$(PHP) vendor/bin/phpstan analyze
phpstan-baseline.neon/clear:
	echo > phpstan-baseline.neon
phpstan-baseline.neon/regenerate: src/SOFe/Capital/Database/RawQueries.php vendor
	$(PHP) vendor/bin/phpstan analyze --generate-baseline

fmt: $(shell find src -type f) .php-cs-fixer.php vendor
	$(PHP) vendor/bin/php-cs-fixer fix $$EXTRA_FLAGS

dev/Capital.phar: $(CAPITAL_SOURCE_FILES) dev/ConsoleScript.php $(CAPITAL_VIRIONS)
	$(PHP) dev/ConsoleScript.php --make plugin.yml,src,resources --out $@

	for file in $(CAPITAL_VIRIONS); do $(PHP) $$file $@ SOFe\\Capital\\Virions\\$$(tr -dc A-Za-z </dev/urandom | head -c 8)\\ ; done

src/SOFe/Capital/Database/RawQueries.php: dev/libasynql.phar resources/mysql/* resources/sqlite/*
	$(PHP) dev/libasynql.phar fx src/ SOFe\\Capital\\Database\\RawQueries --struct 'final class' --spaces 4 --sql resources --prefix capital

dev/composer.phar: Makefile
	cd dev && wget -O - https://getcomposer.org/installer | $(PHP)

vendor: $(COMPOSER) composer.json composer.lock
	$(PHP) $(COMPOSER) install --optimize-autoloader --ignore-platform-reqs
	touch $@

dev/ConsoleScript.php: Makefile
	wget -O $@ https://github.com/pmmp/DevTools/raw/master/src/ConsoleScript.php
	touch $@

dev/libasynql.phar: Makefile
	wget -O $@ https://poggit.pmmp.io/v.dl/poggit/libasynql/libasynql/^4.0.1
	touch $@

dev/rwlock.phar: Makefile
	wget -O $@ https://poggit.pmmp.io/v.dl/sof3/rwlock.php/rwlock.php/^0.1.0
	touch $@

dev/await-generator.phar: Makefile
	wget -O $@ https://poggit.pmmp.io/v.dl/SOF3/await-generator/await-generator/^3.4.1
	touch $@

dev/await-std.phar: Makefile
	wget -O $@ https://poggit.pmmp.io/v.dl/SOF3/await-std/await-std/^0.2.0
	touch $@

dev/SuiteTester.phar: suitetest/plugin/plugin.yml \
	$(shell find suitetest/plugin/src -type f) \
	dev/ConsoleScript.php \
	dev/await-generator.phar dev/await-std.phar
	$(PHP) dev/ConsoleScript.php --make plugin.yml,src --relative suitetest/plugin/ --out $@
	$(PHP) dev/await-generator.phar $@ SOFe\\SuiteTester\\Virions\\$(shell tr -dc A-Za-z </dev/urandom | head -c 8)\\
	$(PHP) dev/await-std.phar $@ SOFe\\SuiteTester\\Virions\\$(shell tr -dc A-Za-z </dev/urandom | head -c 8)\\

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
	$(eval SKIP_MYSQL := $(REUSE_MYSQL) || test -f $@/options/skip-mysql)

	$(SKIP_MYSQL) || docker kill $(CONTAINER_PREFIX)-mysql $(CONTAINER_PREFIX)-pocketmine || true
	$(SKIP_MYSQL) || docker run --rm -d \
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
		-e CAPITAL_DEBUG=1 \
		-u root \
		pmmp/pocketmine-mp:$(POCKETMINE_VERSION) \
		start-pocketmine --debug.level=2
		# bash -c 'chown -R 1000:1000 /data /plugins && su - pocketmine bash -c "start-pocketmine --debug.level=2"'
		#
	docker cp dev/FakePlayer.phar $(CONTAINER_PREFIX)-pocketmine:/plugins/FakePlayer.phar
	docker cp dev/InfoAPI.phar $(CONTAINER_PREFIX)-pocketmine:/plugins/InfoAPI.phar
	docker cp dev/SuiteTester.phar $(CONTAINER_PREFIX)-pocketmine:/plugins/SuiteTester.phar
	docker cp dev/Capital.phar $(CONTAINER_PREFIX)-pocketmine:/plugins/Capital.phar
	docker cp $@/data $(CONTAINER_PREFIX)-pocketmine:/
	docker cp suitetest/shared/data $(CONTAINER_PREFIX)-pocketmine:/

	$(SKIP_MYSQL) || echo Waiting for MySQL to start...
	$(SKIP_MYSQL) || docker exec $(CONTAINER_PREFIX)-mysql bash -c 'while ! mysqladmin ping -u $$MYSQL_USER -p$$MYSQL_PASSWORD --silent 2>/dev/null; do sleep 1; done'
	$(SKIP_MYSQL) || sleep 5

	docker start -ia $(CONTAINER_PREFIX)-pocketmine

	test -d $@/output || mkdir $@/output/

	docker cp $(CONTAINER_PREFIX)-pocketmine:/data/output.json $@/output/output.json
	$(PHP) -r '$$file = $$argv[1]; $$contents = file_get_contents($$file); $$data = json_decode($$contents); $$ok = $$data->ok; if($$ok !== true) exit(1);' $@/output/output.json \
		|| (cat $@/output/output.json && exit 1)

	test ! -f $@/expect-config.yml || docker cp $(CONTAINER_PREFIX)-pocketmine:/data/plugin_data/Capital/config.yml $@/output/actual-config.yml
	test ! -f $@/expect-config.yml || \
		$(DIFF) $@/expect-config.yml $@/output/actual-config.yml || \
		($(SUITE_TESTS_CONFIG_REGEN) && cp $@/output/actual-config.yml $@/expect-config.yml)

	docker cp $(CONTAINER_PREFIX)-pocketmine:/data/plugin_data/Capital/depgraph.dot $@/output/depgraph.dot
	command -v dot && dot -T svg -o $@/output/depgraph.svg $@/output/depgraph.dot || true

debug/suite-mysql:
	docker exec -it capital-suite-mysql-mysql bash -c 'mysql -u $$MYSQL_USER -p$$MYSQL_PASSWORD $$MYSQL_DATABASE'
