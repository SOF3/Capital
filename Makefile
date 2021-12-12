.PHONY: all phpstan

all: src/SOFe/Capital/Database/RawQueries.php phpstan

phpstan:
	vendor/bin/phpstan

# src/SOFe/Capital/Database/Queries.php: libasynql.phar resources/mysql/* resources/sqlite/*
# 	php libasynql.phar def src/ SOFe\\Capital\\Database\\Queries --struct 'final class' --sql resources
src/SOFe/Capital/Database/RawQueries.php: libasynql.phar resources/mysql/* resources/sqlite/*
	php libasynql.phar fx src/ SOFe\\Capital\\Database\\RawQueries --struct 'final class' --sql resources --prefix capital
libasynql.phar: Makefile
	wget -O libasynql.phar https://poggit.pmmp.io/v.dl/poggit/libasynql/libasynql/4.0.0?branch=delimiter
	touch libasynql.phar
