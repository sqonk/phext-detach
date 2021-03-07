# PHEXT Detach

[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%207.3-8892BF.svg)](https://php.net/)
[![License](https://sqonk.com/opensource/license.svg)](license.txt)[![Build Status](https://travis-ci.org/sqonk/phext-detach.svg?branch=master)](https://travis-ci.org/sqonk/phext-detach)

Detach is a library for running tasks inside of a PHP script in parallel using forked processes. It clones a seperate process (based on the parent) and executes the requested callback. 

It is light weight and relies on little more than the PCNTL and APCu PHP extensions plus a minor set of composer packages.

Detach is also minimalist in nature with just a small set of functions and classes to memorise. There is no event system and no architecture to learn, allowing it to fit easily within an existing code structure. 

See the [examples](examples.md) for a quick start guide.

While the spawned tasks have the ability to return data back to the parent there is also the option of using Channels, a loose implementation of channels from the Go language, which provide a simple way of allowing independent processes to send and receive data between one another.

Channels can also be used as logic gates for controlling the execution of various tasks by forcing them to wait for incoming data where required.



## Install

Via Composer

``` bash
$ composer require sqonk/phext-detach
```



### Updating past V1.0

Release 1.1 *changes the value returned* from both a `Channel` and a `BufferedChannel` when they are closed. Previously they would return `NULL`, now they return the constant `CHAN_CLOSED`. This was done in order to clearly differentiate between null values intentionally inserted into a channel and channel closure.

Code written previously akin to the following:

```php
while ($value = $chan->get()) { 
	 // process value.
}
```

Should now be written as follows:

```php
while (($value = $chan->get()) !== CHAN_CLOSED) { 
	 // process value.
}
```

Alternatively, for the purposes of maintaining simpler syntax, you can now use a generator:

```php
// function call..
foreach ($chan->incoming() as $value) {
  // process value.
}

// or directly passing the object as the iterator..
foreach ($chan as $value) {
  // process value.
}
```



### Updating from V0.4

Release 0.5+ adjusts the way Dispatcher::map() works so that it automatically builds and starts the TaskMap, returning the result of `->start()` on the map object. Its parameters have also been expanded to accept the various TaskMap configuration options directly, in preparation for named parameters in PHP8.



### Updating from V0.3

Release 0.4+ is a significant update from previous versions. *It will break existing code that was built to use V0.3.*

Most notably, the class that was formerly named `Channel` has been renamed to `BufferedChannel` and a new `Channel` class has taken its place. You can read more about both classes below.

Also, later in development, the file-based data storage for transferring data between tasks was switched to APCu, now requiring the extension in addition to PCNTL. 



Documentation
------------

[API Reference](docs/api/index.md) now available.




## Examples

Skim through the [example code](examples.md) for a quick start guide on the basic concepts.



## Credits

Theo Howell

Please see original concept of pnctl Threading by Tudor Barbu @ <a href="https://github.com/motanelu/php-thread">motanelu/php-thread</a>



## License

The MIT License (MIT). Please see [License File](license.txt) for more information.
