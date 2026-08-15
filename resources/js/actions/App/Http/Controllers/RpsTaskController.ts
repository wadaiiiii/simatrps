import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\RpsTaskController::store
 * @see app/Http/Controllers/RpsTaskController.php:13
 * @route '/rps/{rps}/tasks'
 */
export const store = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/rps/{rps}/tasks',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RpsTaskController::store
 * @see app/Http/Controllers/RpsTaskController.php:13
 * @route '/rps/{rps}/tasks'
 */
store.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { rps: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                }

    return store.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsTaskController::store
 * @see app/Http/Controllers/RpsTaskController.php:13
 * @route '/rps/{rps}/tasks'
 */
store.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\RpsTaskController::store
 * @see app/Http/Controllers/RpsTaskController.php:13
 * @route '/rps/{rps}/tasks'
 */
    const storeForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsTaskController::store
 * @see app/Http/Controllers/RpsTaskController.php:13
 * @route '/rps/{rps}/tasks'
 */
        storeForm.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\RpsTaskController::update
 * @see app/Http/Controllers/RpsTaskController.php:88
 * @route '/rps/{rps}/tasks/{task}'
 */
export const update = (args: { rps: string | number, task: string | number } | [rps: string | number, task: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/rps/{rps}/tasks/{task}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\RpsTaskController::update
 * @see app/Http/Controllers/RpsTaskController.php:88
 * @route '/rps/{rps}/tasks/{task}'
 */
update.url = (args: { rps: string | number, task: string | number } | [rps: string | number, task: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                    task: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                                task: args.task,
                }

    return update.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace('{task}', parsedArgs.task.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsTaskController::update
 * @see app/Http/Controllers/RpsTaskController.php:88
 * @route '/rps/{rps}/tasks/{task}'
 */
update.put = (args: { rps: string | number, task: string | number } | [rps: string | number, task: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\RpsTaskController::update
 * @see app/Http/Controllers/RpsTaskController.php:88
 * @route '/rps/{rps}/tasks/{task}'
 */
    const updateForm = (args: { rps: string | number, task: string | number } | [rps: string | number, task: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsTaskController::update
 * @see app/Http/Controllers/RpsTaskController.php:88
 * @route '/rps/{rps}/tasks/{task}'
 */
        updateForm.put = (args: { rps: string | number, task: string | number } | [rps: string | number, task: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
/**
* @see \App\Http\Controllers\RpsTaskController::destroy
 * @see app/Http/Controllers/RpsTaskController.php:168
 * @route '/rps/{rps}/tasks/{task}'
 */
export const destroy = (args: { rps: string | number, task: string | number } | [rps: string | number, task: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/rps/{rps}/tasks/{task}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\RpsTaskController::destroy
 * @see app/Http/Controllers/RpsTaskController.php:168
 * @route '/rps/{rps}/tasks/{task}'
 */
destroy.url = (args: { rps: string | number, task: string | number } | [rps: string | number, task: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                    task: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                                task: args.task,
                }

    return destroy.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace('{task}', parsedArgs.task.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsTaskController::destroy
 * @see app/Http/Controllers/RpsTaskController.php:168
 * @route '/rps/{rps}/tasks/{task}'
 */
destroy.delete = (args: { rps: string | number, task: string | number } | [rps: string | number, task: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\RpsTaskController::destroy
 * @see app/Http/Controllers/RpsTaskController.php:168
 * @route '/rps/{rps}/tasks/{task}'
 */
    const destroyForm = (args: { rps: string | number, task: string | number } | [rps: string | number, task: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsTaskController::destroy
 * @see app/Http/Controllers/RpsTaskController.php:168
 * @route '/rps/{rps}/tasks/{task}'
 */
        destroyForm.delete = (args: { rps: string | number, task: string | number } | [rps: string | number, task: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const RpsTaskController = { store, update, destroy }

export default RpsTaskController