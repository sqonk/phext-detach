###### PHEXT > [Detach](../README.md) > [API Reference](index.md) > Channel
------
### Channel
A Channel is a loose implentation of channels from the Go language. It provides a simple way of allowing independant processes to send and receive data between one another.

A Channel is a block-in, and (by default) a block-out mechanism, meaning that the task that sets a value will block until another task has received it.

Bare in mind, that unlike BufferedChannels, for most situations a Channel should be explicitly closed off when there is no more data to send, particularly when other tasks might be locked in a loop or waiting indefinitely for more values. BufferedChannels have the ability to signal their closure prior to script termination but a normal Channel does not, meaning that they have the potential to leave spawned subprocesses hanging after the parent script has since terminated if they are never closed.
#### Methods
[__construct](#__construct)
[close](#close)
[set](#set)
[put](#put)
[get](#get)
[next](#next)
[incoming](#incoming)
[getIterator](#getiterator)

------
##### __construct
```php
public function __construct() 
```
Construct a new Channel.


------
##### close
```php
public function close() : sqonk\phext\detach\Channel
```
Close off the channel, signalling to the receiver that no further values will be sent.


------
##### set
```php
public function set($value) : sqonk\phext\detach\Channel
```
Pass a value into the channel. This method will block until the channel is free to receive new data again.


------
##### put
```php
public function put($value) : sqonk\phext\detach\Channel
```
Alias for Channel::set().


------
##### get
```php
public function get($wait = true) 
```
Obtain the next value on the channel (if any). If $wait is `TRUE` then this method will block until a new value is received. Be aware that in this mode the method will block forever if no further values are sent from other tasks.

If $wait is given as an integer of 1 or more then it is used as a timeout in seconds. In such a case, if nothing is received before the timeout then a value of `NULL` will be returned.

$wait defaults to `TRUE`.


------
##### next
```php
public function next($wait = true) 
```
Alias for Channel::get().


------
##### incoming
```php
public function incoming($wait = true) : Generator
```
Yield the channel out to an iterator loop until the point at which it is closed off. If you wish to put your task into an infinite scanning loop for the lifetime of the channel, for example to process all incoming data, then this can provide a more simplistic model for doing so.

- **$wait** If $wait is given as an integer of 1 or more then it is used as a timeout in seconds. In such a case, if nothing is received before the timeout then a value of `NULL` will be returned if nothing is received prior to the expiry. Defaults to `TRUE`, which means each loop will block until such time as data is received.


------
##### getIterator
```php
public function getIterator() : Traversable
```
Use the channel object as an iterator for incoming values, looping until it is closed off. This method has the same effect as calling Channel::incoming() with the default parameter of `TRUE` for the $wait parameter.


------
