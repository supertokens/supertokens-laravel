FROM rishabhpoddar/supertokens_core_testing

RUN apt update &&  apt install software-properties-common -y

RUN LC_ALL=C.UTF-8 add-apt-repository ppa:ondrej/php && apt update && apt install php7.3 php7.3-xml -y

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer