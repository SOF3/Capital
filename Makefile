PHP = $(shell which php) -dphar.readonly=0

REUSE_MYSQL = false

SUITE_TESTS = suitetest/cases/sqlite suitetest/cases/mysql

CAPITAL_SOURCE_FILES = plugin.yml $(shell find src resources -type f)
CAPITAL_VIRIONS = dev/await-generator.phar dev/await-std.phar dev/libasynql.phar dev/rwlock.phar

.PHONY: all phpstan debug/suite-mysql suitetest $(SUITE_TESTS)

default: phpstan dev/Capital.phar

phpstan: src/SOFe/Capital/Database/RawQueries.php
	$(PHP) vendor/bin/phpstan analyze
phpstan-baseline.neon/clear:
	echo > phpstan-baseline.neon
phpstan-baseline.neon/regenerate: src/SOFe/Capital/Database/RawQueries.php
	$(PHP) vendor/bin/phpstan analyze --generate-baseline

dev/Capital.phar: $(CAPITAL_SOURCE_FILES) dev/ConsoleScript.php $(CAPITAL_VIRIONS)
	$(PHP) dev/ConsoleScript.php --make plugin.yml,src,resources --out $@

	for file in $(CAPITAL_VIRIONS); do $(PHP) $$file $@ SOFe\\Capital\\Virions\\$$(tr -dc A-Za-z </dev/urandom | head -c 16)\\ ; done

src/SOFe/Capital/Database/RawQueries.php: dev/libasynql.phar resources/mysql/* resources/sqlite/*
	$(PHP) dev/libasynql.phar fx src/ SOFe\\Capital\\Database\\RawQueries --struct 'final class' --sql resources --prefix capital

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
		-u root \
		pmmp/pocketmine-mp:4 \
		start-pocketmine --debug.level=2
		# bash -c 'chown -R 1000:1000 /data /plugins && su - pocketmine bash -c "start-pocketmine --debug.level=2"'
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
	docker cp $(CONTAINER_PREFIX)-pocketmine:/data/output.json $@/output.json
	$(PHP) -r '$$file = $$argv[1]; $$contents = file_get_contents($$file); $$data = json_decode($$contents); $$ok = $$data->ok; if($$ok !== true) exit(1);' $@/output.json \
		|| (cat $@/output.json && exit 1)

debug/suite-mysql:
	docker exec -it capital-suite-mysql-mysql bash -c 'mysql -u $$MYSQL_USER -p$$MYSQL_PASSWORD $$MYSQL_DATABASE'
