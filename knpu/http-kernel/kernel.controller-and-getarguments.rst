kernel.controller Event & Controller Arguments
==============================================

Ok guys, what next? Hey, let's dispatch another event::

    // vendor/symfony/symfony/src/Symfony/Component/HttpKernel/HttpKernel.php
    // ...

    private function handleRaw(Request $request, $type = self::MASTER_REQUEST)
    {
        // ...

        $event = new FilterControllerEvent($this, $controller, $request, $type);
        $this->dispatcher->dispatch(KernelEvents::CONTROLLER, $event);
        $controller = $event->getController();

        // ...
    }

This one is called ``kernel.controller``. And like I keep promising, this
one is passed a totally different event object called ``FilterControllerEvent``.
Why would you listen to this event? Well, ``FilterControllerEvent`` has a
``getController()`` method on it. So if you needed to do something based on
what the controller is, or even *change* the controller in a listener, maybe
to mess with your co-workers, a listener to this can do that.

But for understand the Symfony Framework, this is just a hook point. There
are no mission-critical listeners to this event. So let's keep moving!

We have ``$controller``, we can call it, right! Wooooh - slow down. We don't
know what *arguments* to pass to the controller callable yet. To figure that
out, we'll go back to ``ControllerResolver`` into a function called ``getArguments``::

    // vendor/symfony/symfony/src/Symfony/Component/HttpKernel/HttpKernel.php
    // ...

    private function handleRaw(Request $request, $type = self::MASTER_REQUEST)
    {
        // ...

        // controller arguments
        $arguments = $this->resolver->getArguments($request, $controller);

        // ...
    }

Open HttpKernel's ``ControllerResolver`` back up and scroll down to find this.

Arguments Metadata
------------------

This entire ``if`` statement is just a way to get some information about
the arguments to the controller function by using PHP Reflection::

    // vendor/symfony/symfony/src/Symfony/Component/HttpKernel/Controller/ControllerResolver.php
    // ...

    public function getArguments(Request $request, $controller)
    {
        if (is_array($controller)) {
            $r = new \ReflectionMethod($controller[0], $controller[1]);
        } elseif (is_object($controller) && !$controller instanceof \Closure) {
            $r = new \ReflectionObject($controller);
            $r = $r->getMethod('__invoke');
        } else {
            $r = new \ReflectionFunction($controller);
        }

        return $this->doGetArguments($request, $controller, $r->getParameters());
    }

It needs the ``if`` statements because getting that info is different if
your controller is an anonymous function, a method on an object or something
different.

Ultimately, that info about the arguments is what's passed as the ``$parameters``
argument to ``doGetArguments()``. Let me show you what I mean. Let's dump
``$parameters`` and put ``die``::

    // vendor/symfony/symfony/src/Symfony/Component/HttpKernel/Controller/ControllerResolver.php
    // ...

    protected function doGetArguments(Request $request, $controller, array $parameters)
    {
        dump($parameters);die;

        // ...
    }

If we look at ``DinosaurController::showAction``, we have one argument: ``$id``.
The route has ``{id}``, so this is ``$id``: we all know how those match by
name::

    // src/AppBundle/Controller/DinosaurController.php
    // ...

    /**
     * @Route("/dinosaurs/{id}", name="dinosaur_show")
     */
    public function showAction($id)
    {
        // ...
    }

In fact, we're about to see how that works!

So if I go to ``/dinosaurs/22``, you can see that the dumped ``$parameters``
has just one item in it, and in that ``ReflectionParameter`` object is a
name with ``id``, since this is info about the ``$id`` argument:

.. code-block:: text

    array(
        0 => ReflectionObject(
            'name' => 'id',
            // ...
        )
    )

Add a few more arguments - like ``$foo`` and ``$bar`` - and refresh::

    // src/AppBundle/Controller/DinosaurController.php
    // ...

    /**
     * @Route("/dinosaurs/{id}", name="dinosaur_show")
     */
    public function showAction($id, $foo, $bar)
    {
        // ...
    }

Now we have *three* items in ``$parameters``::

    array(
        0 => ReflectionObject(
            'name' => 'id',
            // ...
        ),
        1 => ReflectionObject(
            'name' => 'foo',
            // ...
        ),
        2 => ReflectionObject(
            'name' => 'bar',
            // ...
        )
    )

This one has ``foo`` and this one has ``bar``. So it's just metadata about
what arguments are on that controller function.

Finding Values for Each Argument
--------------------------------

In ``doGetArguments()``, the first thing it does is *so* important and *so*
geeky cool. It goes *back* to ``$request->attributes`` - that same thing
that was populated by the routing. Let's dump this out quickly to remember
what's in there::

    // vendor/symfony/symfony/src/Symfony/Component/HttpKernel/Controller/ControllerResolver.php
    // ...

    protected function doGetArguments(Request $request, $controller, array $parameters)
    {
        $attributes = $request->attributes->all();
        dump($attributes);die;

        // ...
    }

When we refresh, it has ``_controller`` and ``id`` from the routing wildcard:

.. code-block:: text

    array(
        '_controller' => 'AppBundle\Controller\DinosaurController::showAction',
        'id'          => '22',
        '_route'      => 'dinosaur_show',
        '_route_params' => array(...),
    )

It also has a couple of other things that honestly aren't very important.

Keep that array in mind. The ``doGetArguments()`` function iterates over
``$parameters``: the array of info about the arguments to our controller.
And the first thing it does is check to see if the *name* of the parameter -
like ``id`` - exists in the ``$attributes`` array. And if it does, it uses
that value for the argument::

    // vendor/symfony/symfony/src/Symfony/Component/HttpKernel/Controller/ControllerResolver.php
    // ...

    protected function doGetArguments(Request $request, $controller, array $parameters)
    {
        $attributes = $request->attributes->all();
        $arguments = array();

        foreach ($parameters as $param) {
            if (array_key_exists($param->name, $attributes)) {
                $arguments[] = $attributes[$param->name];
            } elseif {
                // ...
            }
        }
        
        return $arguments;
    }

This is exactly why if we have a ``{id}`` inside our route, its value is
passed to a ``$id`` argument in the controller. This is *why* and how that
mapping is done by *name*, because ``ControllerResolver`` say: "Hey, I have
an ``$id`` argument, is there an ``id`` inside the ``$request->attributes``,
which is populated by the routing?"

The Request Argument
~~~~~~~~~~~~~~~~~~~~

And *if* there is nothing in the attributes that matches up, it goes to the
``elseif``: because there's *one* other case. And you've probably seen it
while doing form processing. This is when you have an argument type-hinted
with the ``Request`` class::

    // src/AppBundle/Controller/DinosaurController.php
    // ...
    use Symfony\Component\HttpFoundation\Request;

    /**
     * @Route("/dinosaurs/{id}", name="dinosaur_show")
     */
    public function showAction($id, Request $request)
    {
        // ...
    }

In ControllerResolver, it says: ``if ($param->getClass() && $param->getClass()->isInstance($request))``::

    // vendor/symfony/symfony/src/Symfony/Component/HttpKernel/Controller/ControllerResolver.php
    // ...

    protected function doGetArguments(Request $request, $controller, array $parameters)
    {
        $attributes = $request->attributes->all();
        $arguments = array();

        foreach ($parameters as $param) {
            if (array_key_exists($param->name, $attributes)) {
                $arguments[] = $attributes[$param->name];
            } elseif ($param->getClass() && $param->getClass()->isInstance($request)) {
                $arguments[] = $request;
            }
            // ...
        }
        
        return $arguments;
    }

The ``$param->getClass()`` asks if the argument has a type-hint. The second
part checks to see if the type-hint is for Symfony's ``Request`` class.
If it is, the ``$request`` object is passed to this argument. This is *completely*
special to the Request object: it doesn't work with *anything* else.

But guys, it's really pretty if you think about it. Our whole job is to read
the request and create the response. So, It makes a lot of sense to be able
to have the ``Request`` as an argument to a function that will return the
``Response``. Input ``Request``, output ``Response``.

Errors and Seeing the Arguments in Action
-----------------------------------------

And those are really the only two cases that work. The other ``elseif`` is
there just to see if maybe you have an optional argument::

    // src/AppBundle/Controller/DinosaurController.php
    // ...
    use Symfony\Component\HttpFoundation\Request;

    /**
     * @Route("/dinosaurs/{id}", name="dinosaur_show")
     */
    public function showAction($id, Request $request, $foo = 'defaultValue')
    {
        // ...
    }

And if you do, it uses the default value instead of blowing up::

    // vendor/symfony/symfony/src/Symfony/Component/HttpKernel/Controller/ControllerResolver.php
    // ...

    protected function doGetArguments(Request $request, $controller, array $parameters)
    {
        $attributes = $request->attributes->all();
        $arguments = array();

        foreach ($parameters as $param) {
            if (array_key_exists($param->name, $attributes)) {
                $arguments[] = $attributes[$param->name];
            } elseif ($param->getClass() && $param->getClass()->isInstance($request)) {
                $arguments[] = $request;
            } elseif ($param->isDefaultValueAvailable()) {
                $arguments[] = $param->getDefaultValue();
            } else {
                // it throws an exception
            }
        }
        
        return $arguments;
    }

If all else fails, it tries to get a nice exception message to say: "Hey dude,
you have an argument and I don't know what to pass to it." For example, if
I add a ``$bar`` argument that's not optional, there's no ``{bar}`` in the
route, so we should see this "Controller requires that you provide a value"
error::

    // src/AppBundle/Controller/DinosaurController.php
    // ...
    use Symfony\Component\HttpFoundation\Request;

    /**
     * @Route("/dinosaurs/{id}", name="dinosaur_show")
     */
    public function showAction($id, Request $request, $foo = 'defaultValue', $bar)
    {
        // ...
    }

And we do! That's actually where that comes from.

If we get rid of that, it should pass the request to one argument and it should
be ok with ``$foo`` being optional. When we refresh, it's happy!

Now that we're done, the array of argument values is passed all the way back
to ``HttpKernel``. And now we're dangerous: we have the controller callable
*and* the arguments to pass to it.

Executing the Controller
------------------------

Any ideas on what should happen next? Yep, we *finally* call the controller::

    // vendor/symfony/symfony/src/Symfony/Component/HttpKernel/HttpKernel.php
    // ...

    private function handleRaw(Request $request, $type = self::MASTER_REQUEST)
    {
        // ...

        // call controller
        $response = call_user_func_array($controller, $arguments);

        // ...
    }

So on line 145, that's literally where your controller is executed, and it
passes it all of the arguments.

And what do controllers in Symfony *always* return? They always return a
``Response`` object, and we also see that on line 145.

Unless... they don't return a ``Response``. And that's what we're going to
talk about next.
