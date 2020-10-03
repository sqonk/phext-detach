###### PHEXT > [Detach](../../README.md) > API Reference

------

## API Reference



[Task](Task.md)

Fork a seperate process (based on the parent) and execute the requested callback.



[Dispatcher](Dispatcher.md)

The Dispatch class acts as a static interface to the various classes of the detach library and, along with the global methods, acts as the primary interface for working with seperate tasks.



[TaskMap](TaskMap.md)

The TaskMap class maps an array of elements each unto their own seperate task.



[Channel](Channel.md)

Channel is a loose implentation of channels from the Go language. It provides a simple way of allowing independant processes to send and receive data between one another.



[BufferedChannel](BufferedChannel.md)

A BufferedChannel is an queue of values that may be passed between tasks. Unlike a standard channel, it may continue to accept new values before any existing ones have been read in via another task.



[Global Methods](global_functions.md)

A collection of general purpose utility methods and constants that import across the global namespace.


