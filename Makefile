.PHONY: all phpstan

default: phpstan

phpstan: src/SOFe/Capital/Database/RawQueries.php
	vendor/bin/phpstan analyze
phpstan-baseline.neon/clear:
	echo > phpstan-baseline.neon
phpstan-baseline.neon/regenerate: src/SOFe/Capital/Database/RawQueries.php
	vendor/bin/phpstan analyze --generate-baseline

# src/SOFe/Capital/Database/Queries.php: libasynql.phar resources/mysql/* resources/sqlite/*
# 	php libasynql.phar def src/ SOFe\\Capital\\Database\\Queries --struct 'final class' --sql resources
src/SOFe/Capital/Database/RawQueries.php: libasynql.phar resources/mysql/* resources/sqlite/*
	php libasynql.phar fx src/ SOFe\\Capital\\Database\\RawQueries --struct 'final class' --sql resources --prefix capital
libasynql.phar: Makefile
	wget -O $@ https://poggit.pmmp.io/v.dl/poggit/libasynql/libasynql/4.0.0?branch=delimiter
	touch $@
