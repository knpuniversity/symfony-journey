HttpKernel::handle() The sequel
===============================

So far, the only thing that's really happened is our request attributes have
been modified to have an array with _controller, and also the value of any
wildcards we have inside our route. But that's it. So ultimately, we need
to figure out which controller to call for this - the controller being the
function that we actually write that creates the Response.

The way this is done is here: it uses something called the controller resolver
and calls a method called getController. So hey, let's open that class up
and see how it works! You'll see that there are in fact *two* controller
resolvers: one inside the HttpKernel component and the other is inside
FrameworkBundle. We're going to look at both of them: the one in FrameworkBundle
extends the one in HttpKernel. See BaseControllerResolver is the one that
lives in the component. So the functions we look at will be spread out in
both classes.

The getController function lives in the HttpKernel ControllerResolver, so
let's find it! And remember, a controller is a callable function, that's it.
Symfony's HttpKernel doesn't care if that's an anonymous function or whether
it's a method inside of an object. So you'll see a lot of code here to support
the fact that the controller just needs to be *anything* that's callable.

And this is really cool: what's the first thing it does? It goes out to the
``request->attributes`` and looks for an ``_controller`` key. And the _controller
key is one of those things the RouterListener put into the request attributes.
So why was it called ``_controller`` in the routing layer? Why do we use
``_controller`` in our YAML routes? Simply because, *this* line looks for
an ``_controller`` key. And we can see that if it doesn't find one, this
function doesn't do anything: it panics and exits immediately. 

Almost all the rest of the code in here is basically trying to figure out:
"Hey, is the _controller key in the request attributes maybe already a callable
function?" In our case it's not, and let's dump() it to get a reminder of
what it looks like at this point. Go back to the homepage, refresh, and we
remember that *our* controller is a string, not a *callable* function yet.
It kind of looks like the name of a class and a function, but that's not
technically a callable function yet. 

But in other circumstances, the controller might already be callable. For
example, Silex. In Silex, all of your controllers are anonymous functions
immediately. So it would actually exit earlier in this process.

But ultimately, we don't end up in any of these cases inside the Symfony
Framework and we fall down into this ``createController`` function. This
function lives inside this class, but is overridden by the FrameworkBundle's
ControllerResolver. So let's go to it and find the function.

Ok, this is awesome! Look at the first part: it looks to see if your controller
already has a ``::`` syntax in the middle of it. And if you don't you fall
into this first block. And basically what this does is handle the ``a:b:c``
syntax. So if you're used to using YAML routing, you're used to saying things
like ``AppBundle:Default:index``. In that case, the ``$this->parser`` you
see on line 60 is responsible for taking in that 3-part syntax and giving
us the longer two part syntax, which is what *our's* already has.

.. tip::

    In reality, there is a similar layer that runs during the process of
    compiling routes that converts the ``AppBundle:Default:index`` syntax
    to ``AppBundle\Controller\DefaultController::indexAction``. But the idea
    is still the same - this just makes runtime performance a bit better.

There's also another block below this - around line 63 - that handles the
service syntax. So if you decide to register your controller as a service,
you have a different syntax, and this is the logic that handles that.

Ultimately, we fall down here to the bottom. And one way or another - either
because we have the three-part syntax or because we started with the longer
syntax - we end up with a string, which is the class name, ``::``, and the
method name.

Next, this splits those on the ``::`` and we end up with a ``$class`` variable
and a ``$method`` variable. You don't see the ``list()`` function in PHP
too often, so I'll dump those to be clear. We end up with the ``$class`` set
to the first part and ``$method`` set to everything after the ``::``.

Of course next, it needs to check if we've messed up! Maybe this class doesn't
exist - maybe there's a typo somewhere. It gives us a nice error message
in that case.

Then, on line 79, that's *actually* the line inside Symfony that instantiates
your controller object. We knew that it had to happen somewhere, because our
methods are non-static, and it happens right there. And because Symfony doesn't
know anything about your Controller class, one of the rules - unless you
register your controller as a service - is that your controller class can't
have any constructor arguments. Because you can see - it just says ``new $class()``.

The next line is really really important to just about everything you do
in your controller. It says: if your controller object implements the
ContainerAwareInterface, then call ``$controller->setContainer($container)``.
So if I open up DinosaurController and click to open Symfony base ``Controller``, 
you'll see that it actually extends a ``ContainerAware`` class. Let's click
to open that. And we see that *it* implements the ContainerAwareInterface.
So if our controller extends Symfony base Controller, we implement that
ContainerAwareInterface. Because of that, the ControllerResolver *does*
call setContainer on our controller class, which calls this function here.
And what does it do? It sets that on a protected ``$container`` property.
And this is the reason - let's close a couple of classes - why in any controller
function, we can say ``$this->container->get()`` and then get out whatever
service we want. 

If, for some reasons, you didn't want to extend Symfony's base Controller,
but still wanted access to the container, that would be fine: you'd just
need to implement that ContainerAwareInterface and then have, maybe, a similar
setContainer method that sets it on a ``$container`` property.

Back in ControllerResolver, we now have a ``$controller`` object, we have
the ``$method`` that's going to be called on it, and it returns an array
with those two things. This is a "callable" format in syntax in PHP. This
ultimately goes back to the other ControllerResolver and is returned all
the way back to HttpKernel::handleRaw.

I'm going to close the FrameworkBundle ControllerResolver because we're done
with it, but I'll leave the other one open. Now, ``$controller`` is *some*
callable function. Inside of Symfony, it's going to be an object with a method
name, but in Silex it will be an anonymous function, and it really could be
anything callable.
