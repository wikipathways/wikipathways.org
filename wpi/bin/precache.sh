#!/usr/bin/env bash

# NOTE: change the expression in sed to specify the subdomain
for url in $(curl https://webservice.wikipathways.org/listPathways |\
	xmlstarlet sel -N ns1="http://www.wso2.org/php/xsd" \
		-N ns2="http://www.wikipathways.org/webservice" \
		-t -v '/ns1:listPathwaysResponse/ns1:pathways/ns2:url'); do
	updated_url=$(echo "$url" | sed "s/www.wikipathways.org/dev.wikipathways.org/");
	curl $updated_url > /dev/null;
	sleep 10;
done
