import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\ObeWorkspaceController::store
 * @see app/Http/Controllers/ObeWorkspaceController.php:316
 * @route '/rps/{rps}/materials'
 */
export const store = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/rps/{rps}/materials',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::store
 * @see app/Http/Controllers/ObeWorkspaceController.php:316
 * @route '/rps/{rps}/materials'
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
* @see \App\Http\Controllers\ObeWorkspaceController::store
 * @see app/Http/Controllers/ObeWorkspaceController.php:316
 * @route '/rps/{rps}/materials'
 */
store.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::store
 * @see app/Http/Controllers/ObeWorkspaceController.php:316
 * @route '/rps/{rps}/materials'
 */
    const storeForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ObeWorkspaceController::store
 * @see app/Http/Controllers/ObeWorkspaceController.php:316
 * @route '/rps/{rps}/materials'
 */
        storeForm.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\ObeWorkspaceController::update
 * @see app/Http/Controllers/ObeWorkspaceController.php:346
 * @route '/rps/{rps}/materials/{material}'
 */
export const update = (args: { rps: string | number, material: string | number } | [rps: string | number, material: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/rps/{rps}/materials/{material}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::update
 * @see app/Http/Controllers/ObeWorkspaceController.php:346
 * @route '/rps/{rps}/materials/{material}'
 */
update.url = (args: { rps: string | number, material: string | number } | [rps: string | number, material: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                    material: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                                material: args.material,
                }

    return update.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace('{material}', parsedArgs.material.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ObeWorkspaceController::update
 * @see app/Http/Controllers/ObeWorkspaceController.php:346
 * @route '/rps/{rps}/materials/{material}'
 */
update.put = (args: { rps: string | number, material: string | number } | [rps: string | number, material: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::update
 * @see app/Http/Controllers/ObeWorkspaceController.php:346
 * @route '/rps/{rps}/materials/{material}'
 */
    const updateForm = (args: { rps: string | number, material: string | number } | [rps: string | number, material: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ObeWorkspaceController::update
 * @see app/Http/Controllers/ObeWorkspaceController.php:346
 * @route '/rps/{rps}/materials/{material}'
 */
        updateForm.put = (args: { rps: string | number, material: string | number } | [rps: string | number, material: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\ObeWorkspaceController::importSyllabus
 * @see app/Http/Controllers/ObeWorkspaceController.php:384
 * @route '/rps/{rps}/materials/import-syllabus'
 */
export const importSyllabus = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: importSyllabus.url(args, options),
    method: 'post',
})

importSyllabus.definition = {
    methods: ["post"],
    url: '/rps/{rps}/materials/import-syllabus',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::importSyllabus
 * @see app/Http/Controllers/ObeWorkspaceController.php:384
 * @route '/rps/{rps}/materials/import-syllabus'
 */
importSyllabus.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return importSyllabus.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ObeWorkspaceController::importSyllabus
 * @see app/Http/Controllers/ObeWorkspaceController.php:384
 * @route '/rps/{rps}/materials/import-syllabus'
 */
importSyllabus.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: importSyllabus.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::importSyllabus
 * @see app/Http/Controllers/ObeWorkspaceController.php:384
 * @route '/rps/{rps}/materials/import-syllabus'
 */
    const importSyllabusForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: importSyllabus.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ObeWorkspaceController::importSyllabus
 * @see app/Http/Controllers/ObeWorkspaceController.php:384
 * @route '/rps/{rps}/materials/import-syllabus'
 */
        importSyllabusForm.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: importSyllabus.url(args, options),
            method: 'post',
        })
    
    importSyllabus.form = importSyllabusForm
/**
* @see \App\Http\Controllers\ObeWorkspaceController::destroy
 * @see app/Http/Controllers/ObeWorkspaceController.php:403
 * @route '/rps/{rps}/materials/{material}'
 */
export const destroy = (args: { rps: string | number, material: string | number } | [rps: string | number, material: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/rps/{rps}/materials/{material}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::destroy
 * @see app/Http/Controllers/ObeWorkspaceController.php:403
 * @route '/rps/{rps}/materials/{material}'
 */
destroy.url = (args: { rps: string | number, material: string | number } | [rps: string | number, material: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                    material: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                                material: args.material,
                }

    return destroy.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace('{material}', parsedArgs.material.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ObeWorkspaceController::destroy
 * @see app/Http/Controllers/ObeWorkspaceController.php:403
 * @route '/rps/{rps}/materials/{material}'
 */
destroy.delete = (args: { rps: string | number, material: string | number } | [rps: string | number, material: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::destroy
 * @see app/Http/Controllers/ObeWorkspaceController.php:403
 * @route '/rps/{rps}/materials/{material}'
 */
    const destroyForm = (args: { rps: string | number, material: string | number } | [rps: string | number, material: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ObeWorkspaceController::destroy
 * @see app/Http/Controllers/ObeWorkspaceController.php:403
 * @route '/rps/{rps}/materials/{material}'
 */
        destroyForm.delete = (args: { rps: string | number, material: string | number } | [rps: string | number, material: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const materials = {
    store: Object.assign(store, store),
update: Object.assign(update, update),
importSyllabus: Object.assign(importSyllabus, importSyllabus),
destroy: Object.assign(destroy, destroy),
}

export default materials