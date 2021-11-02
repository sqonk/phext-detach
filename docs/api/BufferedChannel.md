###### PHEXT > [Detach](../README.md) > [API Reference](index.md) > BufferedChannel
------
### BufferedChannel
A BufferedChannel is an queue of values that may be passed between tasks. Unlike a standard channel, it may continue to accept new values before any existing ones have been read in via another task.

The queue is unordered, meaning that values may be read in in a different order from that of which they were put in.

BufferedChannels are an effective bottle-necking system where data obtained from multiple tasks may need to be fed into a singular thread for post-processing.
#### Methods
[__construct](#__construct)
[__destruct](#__destruct)
[capacity](#capacity)
[close](#close)
[set](#set)
[put](#put)
[bulk_set](#bulk_set)
[get](#get)
[next](#next)
[get_all](#get_all)
[incoming](#incoming)
[getIterator](#getiterator)

------
##### __construct
```php
public function __construct() 
```
Construct a new BufferedChannel.


------
##### __destruct
```php
public function __destruct() 
```
No documentation available.


------
##### capacity
```php
public function capacity(int $totalDeposits) : sqonk\phext\detach\BufferedChannel
```
Set an arbitrary limit on the number of times data will be read from the channel. Once the limit has been reached the channel will be closed.

Every time this method is called it will reset the write count to 0.


------
##### close
```php
public function close() : sqonk\phext\detach\BufferedChannel
```
Close off the channel, signalling to the receiver that no further values will be sent.


------
##### set
```php
public function set($value) : sqonk\phext\detach\BufferedChannel
```
Queue a value onto the channel, causing all readers to wake up.


------
##### put
```php
public function put($value) : sqonk\phext\detach\BufferedChannel
```
Alias for Channel::set().


------
##### bulk_set
```php
public function bulk_set(array $values) : sqonk\phext\detach\BufferedChannel
```
Queue a bulk set of values onto the channel, causing all readers to wake up.

If you have a large number of items to push onto the queue at once then this method will be faster than calling set() for every element in the array.


------
##### get
```php
public function get($wait = true) 
```
Obtain the next value on the queue (if any). If $wait is `TRUE` then this method will block until a new value is received. Be aware that in this mode the method will block forever if no further values are queued from other tasks.

If $wait is given as an integer of 1 or more then it is used as a timeout in seconds. In such a case, if nothing is received before the timeout then a value of `NULL` will be returned if nothing is received prior to the expiry.

$wait defaults to `TRUE`.


------
##### next
```php
public function next($wait = true) 
```
Alias for Channel::get().


------
##### get_all
```php
public function get_all($wait = true) 
```
Obtain all values currently residing on the queue (if any). If $wait is `TRUE` then this method will block until a new value is received. Be aware that in this mode the method will block forever if no further values are queued from other tasks.

If $wait is given as an integer of 1 or more then it is used as a timeout in seconds. In such a case, if nothing is received before the timeout then a value of `NULL` will be returned.

$wait defaults to `TRUE`.


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
Use the channel object as an iterator for incoming values, looping until it is closed off. This method has the same effect as calling BufferedChannel::incoming() with the default parameter of `TRUE` for the $wait parameter.


------
