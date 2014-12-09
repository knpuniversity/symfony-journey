Creating and Understanding Magic with HttpKernel
================================================

As we remember, Symfony ultimately executes the ``_controller`` key in the 
request attributes. This is set by the router listener but it could be set
anywhere. 

So let's go adventuring in here. Instead of user agent subscriber
which is a listener to kernel.request one of the things we can do is leverage 
the power now that we understand things. For example, We could just replace the 
``_controller`` key with something else. So let's do that, let's say 

$request->attributes->set('_controller', function(){

});

and then what we can do is set that to any callable function. Ultimately this is
going to be what Symfony thinks is our _controller key. So hey, let's just leverage
the anonymous functions here and return a normal response. 

return new Response('Hello world!')

So that's it! When we refresh, would you believe it? That actually works! So in 
reality I'll show you by commenting this out. Even within listeners to a single 
event there is an order of things. If we go down to events here you're going
to see that out of kernel.request our listener by default, and you can of course
control this, is executed last. The router listener is executed before us, so
it actually runs the routing, sets the _controller key and then our listener
is replacing that. But even if our listener were being run before the router
there is a special property inside of our router listener which is going to become
really important when we talk about sub requests next. And that's the first
thing the router listener does is check to see if the _controller key is 
already set, and if it is the routing never actually runs. 

In our case if our user agent subscriber were run before the router listener it 
would still work because the router listener wouldn't actually do anything. 

And of course since this is a valid controller function we can still use
arguments on it just like before. So if I go to dinosaurs/22, that means we have
a {id} in the argument so we can say, id there and id there and that's 
going to work. So all controllers are created equal. Awesome!

Now, let's comment that out temporarily, or more likely permanently. 

Here's our next challenge: Let's pretend that it's really important
for us to know if the user via the user agent is on a Mac or not. We're really 
wanting to start measuring how hipster our userbase is. In fact, it's so important 
that in many controllers all over our system we want to be able to have an ``$ismac`` 
argument. Just as an example I'm going to put ``$ismac`` into indexAction 
we'll pass that here into our template. Inside of our template let's just make 
use of that so we can see it. So, if isMac, otherwise we'll print out a threatening message. 

Back to the homepage, if we try this now you can expect what's going to happen,
we get an error because there is no {$ismac} in the route, so Symfony
has no idea what to pass into the argument. And up until now that's been the rule.
Whatever has been in our curly brace here is available as an argument and there's
no exceptions to that except for the request object. But that's not really true
because we know it's not about the routing layer, the arguments to your controller
come from the request attributes. And sure, the only thing that actually modifies
the request attributes normally is the routing layer, but there's nothing stopping
any other listener from adding from adding additional requests to attributes. 

First thing inside of our subscriber let's get an $isMac. We'll look for the
user agent, we'll look for the word mac. Let's see if that doesn't equal false.
So to make this available as an argument we need to put it in the request
attributes. And that's it, if we refresh now it actually works perfectly. 

So modifying the request attributes is the key to making things available
as an argument. Just to prove that things are working correctly let's change
that to mac2 and I am on a mac so that goes away. 

So why is this important? Because understanding the core of Symfony is making
you do things that you never dreamed were possible. It's also going to let you
figure out how magic works in case you are using some bundle. For example let's
look at the SensioFrameworkExtraBundle which is basically a bundle that brings 
in a bunch of shortcuts. So if you think about them, now that we've gone through
the request response flow we can explain the magic between all of these shortcuts.

The one I want to look at now is the paramconvertor. If you haven't used the 
paramconvertor before it works like this, you can see that it has a post argument
but you can see there is no curly brace post inside of the URL so this should
throw an error. So what the param convertor does via a listener of course, it 
makes sense now, it grabs the id off of the request attributes, queries for a POST
object with that and then puts a new post request attribute onto that which is
set to the object. And just by doing that the showAction has access to that POST
object. 

So after going through all of this stuff I hope you're feeling really comfortable
with it. We're going to go into one more thing before we talk about the container
and that's going to be sub requests. 

One little reminder whenever you are playing with your site and you go into the
profiler there is a request tab here which is more interesting than before because
it actually has the request attributes. You can see the _controller, the routes stuff
and the isMac stuff. By the way not that it's necessarily useful but the fact that
there is an _route key, it does mean that you can have a $_route argument to your
controller. So any request attributes are available to be set as arguments.




