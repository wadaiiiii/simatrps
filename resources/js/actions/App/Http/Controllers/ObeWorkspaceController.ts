import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\ObeWorkspaceController::storeCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:16
 * @route '/rps/{rps}/cpmk'
 */
export const storeCpmk = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeCpmk.url(args, options),
    method: 'post',
})

storeCpmk.definition = {
    methods: ["post"],
    url: '/rps/{rps}/cpmk',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::storeCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:16
 * @route '/rps/{rps}/cpmk'
 */
storeCpmk.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return storeCpmk.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ObeWorkspaceController::storeCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:16
 * @route '/rps/{rps}/cpmk'
 */
storeCpmk.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeCpmk.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::storeCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:16
 * @route '/rps/{rps}/cpmk'
 */
    const storeCpmkForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: storeCpmk.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ObeWorkspaceController::storeCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:16
 * @route '/rps/{rps}/cpmk'
 */
        storeCpmkForm.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: storeCpmk.url(args, options),
            method: 'post',
        })
    
    storeCpmk.form = storeCpmkForm
/**
* @see \App\Http\Controllers\ObeWorkspaceController::updateCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:45
 * @route '/rps/{rps}/cpmk/{cpmk}'
 */
export const updateCpmk = (args: { rps: string | number, cpmk: string | number } | [rps: string | number, cpmk: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateCpmk.url(args, options),
    method: 'put',
})

updateCpmk.definition = {
    methods: ["put"],
    url: '/rps/{rps}/cpmk/{cpmk}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::updateCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:45
 * @route '/rps/{rps}/cpmk/{cpmk}'
 */
updateCpmk.url = (args: { rps: string | number, cpmk: string | number } | [rps: string | number, cpmk: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                    cpmk: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                                cpmk: args.cpmk,
                }

    return updateCpmk.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace('{cpmk}', parsedArgs.cpmk.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ObeWorkspaceController::updateCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:45
 * @route '/rps/{rps}/cpmk/{cpmk}'
 */
updateCpmk.put = (args: { rps: string | number, cpmk: string | number } | [rps: string | number, cpmk: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateCpmk.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::updateCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:45
 * @route '/rps/{rps}/cpmk/{cpmk}'
 */
    const updateCpmkForm = (args: { rps: string | number, cpmk: string | number } | [rps: string | number, cpmk: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: updateCpmk.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ObeWorkspaceController::updateCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:45
 * @route '/rps/{rps}/cpmk/{cpmk}'
 */
        updateCpmkForm.put = (args: { rps: string | number, cpmk: string | number } | [rps: string | number, cpmk: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: updateCpmk.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    updateCpmk.form = updateCpmkForm
/**
* @see \App\Http\Controllers\ObeWorkspaceController::resetCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:71
 * @route '/rps/{rps}/cpmk/{cpmk}/reset'
 */
export const resetCpmk = (args: { rps: string | number, cpmk: string | number } | [rps: string | number, cpmk: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resetCpmk.url(args, options),
    method: 'post',
})

resetCpmk.definition = {
    methods: ["post"],
    url: '/rps/{rps}/cpmk/{cpmk}/reset',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::resetCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:71
 * @route '/rps/{rps}/cpmk/{cpmk}/reset'
 */
resetCpmk.url = (args: { rps: string | number, cpmk: string | number } | [rps: string | number, cpmk: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                    cpmk: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                                cpmk: args.cpmk,
                }

    return resetCpmk.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace('{cpmk}', parsedArgs.cpmk.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ObeWorkspaceController::resetCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:71
 * @route '/rps/{rps}/cpmk/{cpmk}/reset'
 */
resetCpmk.post = (args: { rps: string | number, cpmk: string | number } | [rps: string | number, cpmk: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resetCpmk.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::resetCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:71
 * @route '/rps/{rps}/cpmk/{cpmk}/reset'
 */
    const resetCpmkForm = (args: { rps: string | number, cpmk: string | number } | [rps: string | number, cpmk: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: resetCpmk.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ObeWorkspaceController::resetCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:71
 * @route '/rps/{rps}/cpmk/{cpmk}/reset'
 */
        resetCpmkForm.post = (args: { rps: string | number, cpmk: string | number } | [rps: string | number, cpmk: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: resetCpmk.url(args, options),
            method: 'post',
        })
    
    resetCpmk.form = resetCpmkForm
/**
* @see \App\Http\Controllers\ObeWorkspaceController::destroyCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:104
 * @route '/rps/{rps}/cpmk/{cpmk}'
 */
export const destroyCpmk = (args: { rps: string | number, cpmk: string | number } | [rps: string | number, cpmk: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyCpmk.url(args, options),
    method: 'delete',
})

destroyCpmk.definition = {
    methods: ["delete"],
    url: '/rps/{rps}/cpmk/{cpmk}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::destroyCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:104
 * @route '/rps/{rps}/cpmk/{cpmk}'
 */
destroyCpmk.url = (args: { rps: string | number, cpmk: string | number } | [rps: string | number, cpmk: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                    cpmk: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                                cpmk: args.cpmk,
                }

    return destroyCpmk.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace('{cpmk}', parsedArgs.cpmk.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ObeWorkspaceController::destroyCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:104
 * @route '/rps/{rps}/cpmk/{cpmk}'
 */
destroyCpmk.delete = (args: { rps: string | number, cpmk: string | number } | [rps: string | number, cpmk: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyCpmk.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::destroyCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:104
 * @route '/rps/{rps}/cpmk/{cpmk}'
 */
    const destroyCpmkForm = (args: { rps: string | number, cpmk: string | number } | [rps: string | number, cpmk: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroyCpmk.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ObeWorkspaceController::destroyCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:104
 * @route '/rps/{rps}/cpmk/{cpmk}'
 */
        destroyCpmkForm.delete = (args: { rps: string | number, cpmk: string | number } | [rps: string | number, cpmk: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroyCpmk.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroyCpmk.form = destroyCpmkForm
/**
* @see \App\Http\Controllers\ObeWorkspaceController::saveCpmkCpl
 * @see app/Http/Controllers/ObeWorkspaceController.php:130
 * @route '/rps/{rps}/cpmk-cpl'
 */
export const saveCpmkCpl = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: saveCpmkCpl.url(args, options),
    method: 'put',
})

saveCpmkCpl.definition = {
    methods: ["put"],
    url: '/rps/{rps}/cpmk-cpl',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::saveCpmkCpl
 * @see app/Http/Controllers/ObeWorkspaceController.php:130
 * @route '/rps/{rps}/cpmk-cpl'
 */
saveCpmkCpl.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return saveCpmkCpl.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ObeWorkspaceController::saveCpmkCpl
 * @see app/Http/Controllers/ObeWorkspaceController.php:130
 * @route '/rps/{rps}/cpmk-cpl'
 */
saveCpmkCpl.put = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: saveCpmkCpl.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::saveCpmkCpl
 * @see app/Http/Controllers/ObeWorkspaceController.php:130
 * @route '/rps/{rps}/cpmk-cpl'
 */
    const saveCpmkCplForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: saveCpmkCpl.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ObeWorkspaceController::saveCpmkCpl
 * @see app/Http/Controllers/ObeWorkspaceController.php:130
 * @route '/rps/{rps}/cpmk-cpl'
 */
        saveCpmkCplForm.put = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: saveCpmkCpl.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    saveCpmkCpl.form = saveCpmkCplForm
/**
* @see \App\Http\Controllers\ObeWorkspaceController::storeSubCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:193
 * @route '/rps/{rps}/sub-cpmk'
 */
export const storeSubCpmk = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeSubCpmk.url(args, options),
    method: 'post',
})

storeSubCpmk.definition = {
    methods: ["post"],
    url: '/rps/{rps}/sub-cpmk',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::storeSubCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:193
 * @route '/rps/{rps}/sub-cpmk'
 */
storeSubCpmk.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return storeSubCpmk.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ObeWorkspaceController::storeSubCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:193
 * @route '/rps/{rps}/sub-cpmk'
 */
storeSubCpmk.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeSubCpmk.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::storeSubCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:193
 * @route '/rps/{rps}/sub-cpmk'
 */
    const storeSubCpmkForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: storeSubCpmk.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ObeWorkspaceController::storeSubCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:193
 * @route '/rps/{rps}/sub-cpmk'
 */
        storeSubCpmkForm.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: storeSubCpmk.url(args, options),
            method: 'post',
        })
    
    storeSubCpmk.form = storeSubCpmkForm
/**
* @see \App\Http\Controllers\ObeWorkspaceController::updateSubCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:242
 * @route '/rps/{rps}/sub-cpmk/{subCpmk}'
 */
export const updateSubCpmk = (args: { rps: string | number, subCpmk: string | number } | [rps: string | number, subCpmk: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateSubCpmk.url(args, options),
    method: 'put',
})

updateSubCpmk.definition = {
    methods: ["put"],
    url: '/rps/{rps}/sub-cpmk/{subCpmk}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::updateSubCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:242
 * @route '/rps/{rps}/sub-cpmk/{subCpmk}'
 */
updateSubCpmk.url = (args: { rps: string | number, subCpmk: string | number } | [rps: string | number, subCpmk: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                    subCpmk: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                                subCpmk: args.subCpmk,
                }

    return updateSubCpmk.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace('{subCpmk}', parsedArgs.subCpmk.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ObeWorkspaceController::updateSubCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:242
 * @route '/rps/{rps}/sub-cpmk/{subCpmk}'
 */
updateSubCpmk.put = (args: { rps: string | number, subCpmk: string | number } | [rps: string | number, subCpmk: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateSubCpmk.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::updateSubCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:242
 * @route '/rps/{rps}/sub-cpmk/{subCpmk}'
 */
    const updateSubCpmkForm = (args: { rps: string | number, subCpmk: string | number } | [rps: string | number, subCpmk: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: updateSubCpmk.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ObeWorkspaceController::updateSubCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:242
 * @route '/rps/{rps}/sub-cpmk/{subCpmk}'
 */
        updateSubCpmkForm.put = (args: { rps: string | number, subCpmk: string | number } | [rps: string | number, subCpmk: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: updateSubCpmk.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    updateSubCpmk.form = updateSubCpmkForm
/**
* @see \App\Http\Controllers\ObeWorkspaceController::destroySubCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:299
 * @route '/rps/{rps}/sub-cpmk/{subCpmk}'
 */
export const destroySubCpmk = (args: { rps: string | number, subCpmk: string | number } | [rps: string | number, subCpmk: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroySubCpmk.url(args, options),
    method: 'delete',
})

destroySubCpmk.definition = {
    methods: ["delete"],
    url: '/rps/{rps}/sub-cpmk/{subCpmk}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::destroySubCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:299
 * @route '/rps/{rps}/sub-cpmk/{subCpmk}'
 */
destroySubCpmk.url = (args: { rps: string | number, subCpmk: string | number } | [rps: string | number, subCpmk: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                    subCpmk: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                                subCpmk: args.subCpmk,
                }

    return destroySubCpmk.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace('{subCpmk}', parsedArgs.subCpmk.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ObeWorkspaceController::destroySubCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:299
 * @route '/rps/{rps}/sub-cpmk/{subCpmk}'
 */
destroySubCpmk.delete = (args: { rps: string | number, subCpmk: string | number } | [rps: string | number, subCpmk: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroySubCpmk.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::destroySubCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:299
 * @route '/rps/{rps}/sub-cpmk/{subCpmk}'
 */
    const destroySubCpmkForm = (args: { rps: string | number, subCpmk: string | number } | [rps: string | number, subCpmk: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroySubCpmk.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ObeWorkspaceController::destroySubCpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:299
 * @route '/rps/{rps}/sub-cpmk/{subCpmk}'
 */
        destroySubCpmkForm.delete = (args: { rps: string | number, subCpmk: string | number } | [rps: string | number, subCpmk: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroySubCpmk.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroySubCpmk.form = destroySubCpmkForm
/**
* @see \App\Http\Controllers\ObeWorkspaceController::storeMaterial
 * @see app/Http/Controllers/ObeWorkspaceController.php:316
 * @route '/rps/{rps}/materials'
 */
export const storeMaterial = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeMaterial.url(args, options),
    method: 'post',
})

storeMaterial.definition = {
    methods: ["post"],
    url: '/rps/{rps}/materials',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::storeMaterial
 * @see app/Http/Controllers/ObeWorkspaceController.php:316
 * @route '/rps/{rps}/materials'
 */
storeMaterial.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return storeMaterial.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ObeWorkspaceController::storeMaterial
 * @see app/Http/Controllers/ObeWorkspaceController.php:316
 * @route '/rps/{rps}/materials'
 */
storeMaterial.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeMaterial.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::storeMaterial
 * @see app/Http/Controllers/ObeWorkspaceController.php:316
 * @route '/rps/{rps}/materials'
 */
    const storeMaterialForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: storeMaterial.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ObeWorkspaceController::storeMaterial
 * @see app/Http/Controllers/ObeWorkspaceController.php:316
 * @route '/rps/{rps}/materials'
 */
        storeMaterialForm.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: storeMaterial.url(args, options),
            method: 'post',
        })
    
    storeMaterial.form = storeMaterialForm
/**
* @see \App\Http\Controllers\ObeWorkspaceController::updateMaterial
 * @see app/Http/Controllers/ObeWorkspaceController.php:346
 * @route '/rps/{rps}/materials/{material}'
 */
export const updateMaterial = (args: { rps: string | number, material: string | number } | [rps: string | number, material: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateMaterial.url(args, options),
    method: 'put',
})

updateMaterial.definition = {
    methods: ["put"],
    url: '/rps/{rps}/materials/{material}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::updateMaterial
 * @see app/Http/Controllers/ObeWorkspaceController.php:346
 * @route '/rps/{rps}/materials/{material}'
 */
updateMaterial.url = (args: { rps: string | number, material: string | number } | [rps: string | number, material: string | number ], options?: RouteQueryOptions) => {
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

    return updateMaterial.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace('{material}', parsedArgs.material.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ObeWorkspaceController::updateMaterial
 * @see app/Http/Controllers/ObeWorkspaceController.php:346
 * @route '/rps/{rps}/materials/{material}'
 */
updateMaterial.put = (args: { rps: string | number, material: string | number } | [rps: string | number, material: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateMaterial.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::updateMaterial
 * @see app/Http/Controllers/ObeWorkspaceController.php:346
 * @route '/rps/{rps}/materials/{material}'
 */
    const updateMaterialForm = (args: { rps: string | number, material: string | number } | [rps: string | number, material: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: updateMaterial.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ObeWorkspaceController::updateMaterial
 * @see app/Http/Controllers/ObeWorkspaceController.php:346
 * @route '/rps/{rps}/materials/{material}'
 */
        updateMaterialForm.put = (args: { rps: string | number, material: string | number } | [rps: string | number, material: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: updateMaterial.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    updateMaterial.form = updateMaterialForm
/**
* @see \App\Http\Controllers\ObeWorkspaceController::importSyllabusMaterials
 * @see app/Http/Controllers/ObeWorkspaceController.php:384
 * @route '/rps/{rps}/materials/import-syllabus'
 */
export const importSyllabusMaterials = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: importSyllabusMaterials.url(args, options),
    method: 'post',
})

importSyllabusMaterials.definition = {
    methods: ["post"],
    url: '/rps/{rps}/materials/import-syllabus',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::importSyllabusMaterials
 * @see app/Http/Controllers/ObeWorkspaceController.php:384
 * @route '/rps/{rps}/materials/import-syllabus'
 */
importSyllabusMaterials.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return importSyllabusMaterials.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ObeWorkspaceController::importSyllabusMaterials
 * @see app/Http/Controllers/ObeWorkspaceController.php:384
 * @route '/rps/{rps}/materials/import-syllabus'
 */
importSyllabusMaterials.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: importSyllabusMaterials.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::importSyllabusMaterials
 * @see app/Http/Controllers/ObeWorkspaceController.php:384
 * @route '/rps/{rps}/materials/import-syllabus'
 */
    const importSyllabusMaterialsForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: importSyllabusMaterials.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ObeWorkspaceController::importSyllabusMaterials
 * @see app/Http/Controllers/ObeWorkspaceController.php:384
 * @route '/rps/{rps}/materials/import-syllabus'
 */
        importSyllabusMaterialsForm.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: importSyllabusMaterials.url(args, options),
            method: 'post',
        })
    
    importSyllabusMaterials.form = importSyllabusMaterialsForm
/**
* @see \App\Http\Controllers\ObeWorkspaceController::destroyMaterial
 * @see app/Http/Controllers/ObeWorkspaceController.php:403
 * @route '/rps/{rps}/materials/{material}'
 */
export const destroyMaterial = (args: { rps: string | number, material: string | number } | [rps: string | number, material: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyMaterial.url(args, options),
    method: 'delete',
})

destroyMaterial.definition = {
    methods: ["delete"],
    url: '/rps/{rps}/materials/{material}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::destroyMaterial
 * @see app/Http/Controllers/ObeWorkspaceController.php:403
 * @route '/rps/{rps}/materials/{material}'
 */
destroyMaterial.url = (args: { rps: string | number, material: string | number } | [rps: string | number, material: string | number ], options?: RouteQueryOptions) => {
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

    return destroyMaterial.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace('{material}', parsedArgs.material.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ObeWorkspaceController::destroyMaterial
 * @see app/Http/Controllers/ObeWorkspaceController.php:403
 * @route '/rps/{rps}/materials/{material}'
 */
destroyMaterial.delete = (args: { rps: string | number, material: string | number } | [rps: string | number, material: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyMaterial.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::destroyMaterial
 * @see app/Http/Controllers/ObeWorkspaceController.php:403
 * @route '/rps/{rps}/materials/{material}'
 */
    const destroyMaterialForm = (args: { rps: string | number, material: string | number } | [rps: string | number, material: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroyMaterial.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ObeWorkspaceController::destroyMaterial
 * @see app/Http/Controllers/ObeWorkspaceController.php:403
 * @route '/rps/{rps}/materials/{material}'
 */
        destroyMaterialForm.delete = (args: { rps: string | number, material: string | number } | [rps: string | number, material: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroyMaterial.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroyMaterial.form = destroyMaterialForm
/**
* @see \App\Http\Controllers\ObeWorkspaceController::updateWeek
 * @see app/Http/Controllers/ObeWorkspaceController.php:560
 * @route '/rps/{rps}/weeks/{week}'
 */
export const updateWeek = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateWeek.url(args, options),
    method: 'put',
})

updateWeek.definition = {
    methods: ["put"],
    url: '/rps/{rps}/weeks/{week}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::updateWeek
 * @see app/Http/Controllers/ObeWorkspaceController.php:560
 * @route '/rps/{rps}/weeks/{week}'
 */
updateWeek.url = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                    week: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                                week: args.week,
                }

    return updateWeek.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace('{week}', parsedArgs.week.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ObeWorkspaceController::updateWeek
 * @see app/Http/Controllers/ObeWorkspaceController.php:560
 * @route '/rps/{rps}/weeks/{week}'
 */
updateWeek.put = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateWeek.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::updateWeek
 * @see app/Http/Controllers/ObeWorkspaceController.php:560
 * @route '/rps/{rps}/weeks/{week}'
 */
    const updateWeekForm = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: updateWeek.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ObeWorkspaceController::updateWeek
 * @see app/Http/Controllers/ObeWorkspaceController.php:560
 * @route '/rps/{rps}/weeks/{week}'
 */
        updateWeekForm.put = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: updateWeek.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    updateWeek.form = updateWeekForm
/**
* @see \App\Http\Controllers\ObeWorkspaceController::alignSubCpmkSequence
 * @see app/Http/Controllers/ObeWorkspaceController.php:420
 * @route '/rps/{rps}/weeks/align-subcpmk'
 */
export const alignSubCpmkSequence = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: alignSubCpmkSequence.url(args, options),
    method: 'post',
})

alignSubCpmkSequence.definition = {
    methods: ["post"],
    url: '/rps/{rps}/weeks/align-subcpmk',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::alignSubCpmkSequence
 * @see app/Http/Controllers/ObeWorkspaceController.php:420
 * @route '/rps/{rps}/weeks/align-subcpmk'
 */
alignSubCpmkSequence.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return alignSubCpmkSequence.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ObeWorkspaceController::alignSubCpmkSequence
 * @see app/Http/Controllers/ObeWorkspaceController.php:420
 * @route '/rps/{rps}/weeks/align-subcpmk'
 */
alignSubCpmkSequence.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: alignSubCpmkSequence.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::alignSubCpmkSequence
 * @see app/Http/Controllers/ObeWorkspaceController.php:420
 * @route '/rps/{rps}/weeks/align-subcpmk'
 */
    const alignSubCpmkSequenceForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: alignSubCpmkSequence.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ObeWorkspaceController::alignSubCpmkSequence
 * @see app/Http/Controllers/ObeWorkspaceController.php:420
 * @route '/rps/{rps}/weeks/align-subcpmk'
 */
        alignSubCpmkSequenceForm.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: alignSubCpmkSequence.url(args, options),
            method: 'post',
        })
    
    alignSubCpmkSequence.form = alignSubCpmkSequenceForm
/**
* @see \App\Http\Controllers\ObeWorkspaceController::applyTimeStandard
 * @see app/Http/Controllers/ObeWorkspaceController.php:463
 * @route '/rps/{rps}/weeks/apply-time-standard'
 */
export const applyTimeStandard = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: applyTimeStandard.url(args, options),
    method: 'post',
})

applyTimeStandard.definition = {
    methods: ["post"],
    url: '/rps/{rps}/weeks/apply-time-standard',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::applyTimeStandard
 * @see app/Http/Controllers/ObeWorkspaceController.php:463
 * @route '/rps/{rps}/weeks/apply-time-standard'
 */
applyTimeStandard.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return applyTimeStandard.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ObeWorkspaceController::applyTimeStandard
 * @see app/Http/Controllers/ObeWorkspaceController.php:463
 * @route '/rps/{rps}/weeks/apply-time-standard'
 */
applyTimeStandard.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: applyTimeStandard.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::applyTimeStandard
 * @see app/Http/Controllers/ObeWorkspaceController.php:463
 * @route '/rps/{rps}/weeks/apply-time-standard'
 */
    const applyTimeStandardForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: applyTimeStandard.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ObeWorkspaceController::applyTimeStandard
 * @see app/Http/Controllers/ObeWorkspaceController.php:463
 * @route '/rps/{rps}/weeks/apply-time-standard'
 */
        applyTimeStandardForm.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: applyTimeStandard.url(args, options),
            method: 'post',
        })
    
    applyTimeStandard.form = applyTimeStandardForm
/**
* @see \App\Http\Controllers\ObeWorkspaceController::normalizeReferences
 * @see app/Http/Controllers/ObeWorkspaceController.php:491
 * @route '/rps/{rps}/weeks/normalize-references'
 */
export const normalizeReferences = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: normalizeReferences.url(args, options),
    method: 'post',
})

normalizeReferences.definition = {
    methods: ["post"],
    url: '/rps/{rps}/weeks/normalize-references',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::normalizeReferences
 * @see app/Http/Controllers/ObeWorkspaceController.php:491
 * @route '/rps/{rps}/weeks/normalize-references'
 */
normalizeReferences.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return normalizeReferences.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ObeWorkspaceController::normalizeReferences
 * @see app/Http/Controllers/ObeWorkspaceController.php:491
 * @route '/rps/{rps}/weeks/normalize-references'
 */
normalizeReferences.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: normalizeReferences.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::normalizeReferences
 * @see app/Http/Controllers/ObeWorkspaceController.php:491
 * @route '/rps/{rps}/weeks/normalize-references'
 */
    const normalizeReferencesForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: normalizeReferences.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ObeWorkspaceController::normalizeReferences
 * @see app/Http/Controllers/ObeWorkspaceController.php:491
 * @route '/rps/{rps}/weeks/normalize-references'
 */
        normalizeReferencesForm.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: normalizeReferences.url(args, options),
            method: 'post',
        })
    
    normalizeReferences.form = normalizeReferencesForm
const ObeWorkspaceController = { storeCpmk, updateCpmk, resetCpmk, destroyCpmk, saveCpmkCpl, storeSubCpmk, updateSubCpmk, destroySubCpmk, storeMaterial, updateMaterial, importSyllabusMaterials, destroyMaterial, updateWeek, alignSubCpmkSequence, applyTimeStandard, normalizeReferences }

export default ObeWorkspaceController